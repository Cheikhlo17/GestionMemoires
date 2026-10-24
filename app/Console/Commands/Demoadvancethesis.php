<?php

namespace App\Console\Commands;

use App\Exceptions\SchedulingConflictException;
use App\Models\DefenseJuryEvaluation;
use App\Models\DefenseRoom;
use App\Models\DefenseSchedule;
use App\Models\JuryMember;
use App\Models\Supervisor;
use App\Models\Thesis;
use App\Models\ThesisArchive;
use App\Models\User;
use App\Services\Interfaces\DefenseScheduleServiceInterface;
use App\Services\Interfaces\ThesisArchiveServiceInterface;
use App\Services\Interfaces\ThesisServiceInterface;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Commande de DÉMONSTRATION / TEST (jamais en production).
 *
 * Fait avancer un mémoire existant, à partir de sa première version déposée,
 * jusqu'à l'état « prêt à archiver », en appelant les mêmes services que
 * l'interface (aucun statut n'est écrit à la main). Chaque étape est
 * idempotente : relancer la commande reprend là où elle s'est arrêtée.
 */
class DemoAdvanceThesis extends Command
{
    protected $signature = 'suma:demo-advance
        {thesis : Identifiant du mémoire}
        {--archive : Archive aussi le mémoire à la fin}
        {--visibility=public : Visibilité de l\'archive (public ou private)}
        {--keywords= : Mots-clés séparés par des virgules}
        {--yes : Ne pas demander de confirmation}';

    protected $description = 'Fait avancer un mémoire de test, de la première version jusqu\'à « prêt à archiver » (jamais en production)';

    public function handle(
        ThesisServiceInterface $theses,
        DefenseScheduleServiceInterface $defenses,
        ThesisArchiveServiceInterface $archives,
    ): int {
        if (app()->environment('production')) {
            $this->components->error('Commande interdite en production.');

            return self::FAILURE;
        }

        $visibility = (string) $this->option('visibility');

        if (!in_array($visibility, ['public', 'private'], true)) {
            $this->components->error('--visibility doit valoir « public » ou « private ».');

            return self::FAILURE;
        }

        $thesis = Thesis::with(['student.user', 'supervisor.user', 'department'])->find($this->argument('thesis'));

        if (!$thesis) {
            $this->components->error('Mémoire introuvable.');

            return self::FAILURE;
        }

        $alreadyArchived = ThesisArchive::where('thesis_id', $thesis->id)->exists();

        if ($alreadyArchived) {
            $this->components->info('Ce mémoire est déjà archivé. Pour rejouer le test : php artisan suma:demo-reset ' . $thesis->id . ' --to=approved');

            return self::SUCCESS;
        }

        if ($thesis->status === Thesis::STATUS_ARCHIVED) {
            $this->components->error('Statut « archived » sans archive réelle (statut modifié à la main). Corrigez avec : php artisan suma:demo-reset ' . $thesis->id . ' --to=approved');

            return self::FAILURE;
        }

        if ($thesis->status === Thesis::STATUS_REJECTED) {
            $this->components->error('Le mémoire est rejeté. Utilisez d\'abord : php artisan suma:demo-reset ' . $thesis->id . ' --to=submitted');

            return self::FAILURE;
        }

        if (!$thesis->versions()->where('mime_type', 'application/pdf')->exists()) {
            $this->components->error('Aucune version PDF déposée : téléversez au moins un PDF depuis l\'interface (un DOCX ne permet pas l\'archivage).');

            return self::FAILURE;
        }

        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'administrator'))
            ->where('is_active', true)
            ->first();

        if (!$admin) {
            $this->components->error('Aucun administrateur actif trouvé.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Mémoire', "#{$thesis->id} — {$thesis->title}");
        $this->components->twoColumnDetail('Étudiant', $thesis->student->user->full_name);
        $this->components->twoColumnDetail('Statut actuel', $thesis->status);

        if (!$this->option('yes') && !$this->confirm('Faire avancer ce mémoire avec des données de démonstration ?', true)) {
            return self::SUCCESS;
        }

        try {
            $thesis = $this->assignSupervisorIfMissing($theses, $thesis, $admin);
            $thesis = $this->advanceToApproved($theses, $thesis, $admin);
            $schedule = $this->ensureDefense($defenses, $thesis, $admin);
            $this->recordEvaluations($schedule);
            $this->recordFinalResult($defenses, $schedule, $admin);

            $thesis = $thesis->fresh();
            $reasons = $archives->checkEligibility($thesis);

            if ($this->option('archive')) {
                if (!empty($reasons)) {
                    throw new RuntimeException('Mémoire non éligible : ' . implode(' | ', $reasons));
                }

                $keywords = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('keywords')))));

                $archive = $archives->archive($thesis, [
                    'visibility' => $visibility,
                    'keywords' => $keywords ?: ['démonstration'],
                    'show_final_grade' => false,
                ], $admin);

                $this->step("Mémoire archivé ({$visibility}) — archive #{$archive->id}");
            } elseif (empty($reasons)) {
                $this->components->info('Prêt à archiver. Ouvrez : Administration → Archives → « Mémoires prêts à être archivés ».');
            } else {
                $this->components->warn('Pas encore éligible : ' . implode(' | ', $reasons));
            }
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Terminé. Les e-mails de notification sont traités par « php artisan queue:work » (voir storage/logs/laravel.log).');

        return self::SUCCESS;
    }

    private function assignSupervisorIfMissing(ThesisServiceInterface $theses, Thesis $thesis, User $admin): Thesis
    {
        if ($thesis->supervisor_id) {
            return $thesis;
        }

        $supervisor = Supervisor::with('user')
            ->where('department_id', $thesis->department_id)
            ->where('is_active', true)
            ->first();

        if (!$supervisor) {
            throw new RuntimeException('Aucun encadreur actif dans ce département. Lancez : php artisan db:seed --class=DemoAccountSeeder');
        }

        $thesis = $theses->assignSupervisor($thesis, $supervisor->id, $admin);
        $this->step("Encadreur assigné : {$supervisor->user->full_name}");

        return $thesis->fresh(['student.user', 'supervisor.user', 'department']);
    }

    private function advanceToApproved(ThesisServiceInterface $theses, Thesis $thesis, User $admin): Thesis
    {
        $thesis = $thesis->fresh(['student.user', 'supervisor.user', 'department']);
        $reviewer = $thesis->supervisor?->user ?? $admin;

        while ($thesis->status !== Thesis::STATUS_APPROVED) {
            switch ($thesis->status) {
                case Thesis::STATUS_DRAFT:
                case Thesis::STATUS_REVISION_REQUIRED:
                    $thesis = $theses->submit($thesis, $admin);
                    $this->step('Mémoire soumis');
                    break;

                case Thesis::STATUS_SUBMITTED:
                    $thesis = $theses->changeStatus($thesis, Thesis::STATUS_UNDER_REVIEW, $reviewer, '[DÉMO] Prise en charge par l\'encadreur.');
                    $this->step('Passé en révision');
                    break;

                case Thesis::STATUS_UNDER_REVIEW:
                    $thesis = $theses->changeStatus($thesis, Thesis::STATUS_APPROVED, $reviewer, '[DÉMO] Mémoire validé par l\'encadreur.');
                    $this->step('Mémoire approuvé');
                    break;

                default:
                    throw new RuntimeException("Statut « {$thesis->status} » non géré : utilisez suma:demo-reset.");
            }
        }

        return $thesis->fresh(['student.user', 'supervisor.user', 'department']);
    }

    private function ensureDefense(DefenseScheduleServiceInterface $defenses, Thesis $thesis, User $admin): DefenseSchedule
    {
        $schedule = DefenseSchedule::with(['juryMembers.user', 'room'])->where('thesis_id', $thesis->id)->first();

        if ($schedule && $schedule->status === DefenseSchedule::STATUS_CANCELLED) {
            throw new RuntimeException('La soutenance existante est annulée : utilisez suma:demo-reset.');
        }

        if (!$schedule) {
            $room = DefenseRoom::where('is_active', true)->first();

            if (!$room) {
                throw new RuntimeException('Aucune salle active. Lancez : php artisan db:seed --class=DefenseRoomSeeder');
            }

            $schedule = $this->scheduleWithFreeSlot($defenses, $thesis, $room, $this->buildJuryAssignments($thesis), $admin);
            $this->step("Soutenance planifiée le {$schedule->scheduled_at->format('d/m/Y H:i')} ({$room->name}) + jury assigné");

            return $schedule->load(['juryMembers.user', 'room']);
        }

        if ($schedule->juryMembers->count() < 3) {
            $schedule = $defenses->assignJury($schedule, $this->buildJuryAssignments($thesis), $admin);
            $this->step('Jury assigné');
        }

        return $schedule->load(['juryMembers.user', 'room']);
    }

    /**
     * @return array<int, array{jury_member_id: int, role: string}>
     */
    private function buildJuryAssignments(Thesis $thesis): array
    {
        $supervisorUserId = $thesis->supervisor?->user_id;

        $members = JuryMember::where('is_active', true)
            ->when($supervisorUserId, fn ($q) => $q->where('user_id', '!=', $supervisorUserId))
            ->orderBy('id')
            ->take(3)
            ->get();

        if ($members->count() < 3) {
            throw new RuntimeException('Il faut au moins 3 membres de jury actifs (hors encadreur). Lancez : php artisan db:seed --class=DemoAccountSeeder');
        }

        $roles = ['president', 'examiner', 'reporter'];

        $assignments = [];

        foreach ($members->values() as $index => $member) {
            $assignments[] = [
                'jury_member_id' => $member->id,
                'role' => $roles[$index],
            ];
        }

        return $assignments;
    }

    private function scheduleWithFreeSlot(
        DefenseScheduleServiceInterface $defenses,
        Thesis $thesis,
        DefenseRoom $room,
        array $assignments,
        User $admin
    ): DefenseSchedule {
        for ($daysAgo = 1; $daysAgo <= 30; $daysAgo++) {
            $slot = now()->subDays($daysAgo)->setTime(10, 0);

            try {
                $schedule = $defenses->schedule([
                    'thesis_id' => $thesis->id,
                    'defense_room_id' => $room->id,
                    'scheduled_at' => $slot->toDateTimeString(),
                    'duration_minutes' => 60,
                ], $admin);
            } catch (SchedulingConflictException) {
                continue;
            }

            try {
                return $defenses->assignJury($schedule, $assignments, $admin);
            } catch (SchedulingConflictException) {
                $schedule->delete();
            }
        }

        throw new RuntimeException('Aucun créneau libre trouvé sur les 30 derniers jours.');
    }

    private function recordEvaluations(DefenseSchedule $schedule): void
    {
        $grades = [15.0, 16.0, 14.5];

        foreach ($schedule->juryMembers->values() as $index => $member) {
            DefenseJuryEvaluation::updateOrCreate(
                ['defense_schedule_id' => $schedule->id, 'jury_member_id' => $member->id],
                [
                    'grade' => $grades[$index % count($grades)],
                    'verdict' => 'pass',
                    'remarks' => '[DÉMO] Évaluation générée par la commande de démonstration.',
                    'submitted_at' => now(),
                ]
            );
        }

        $this->step('Évaluations individuelles du jury enregistrées');
    }

    private function recordFinalResult(DefenseScheduleServiceInterface $defenses, DefenseSchedule $schedule, User $admin): void
    {
        $schedule->refresh();

        if ($schedule->status === DefenseSchedule::STATUS_COMPLETED) {
            return;
        }

        $average = round((float) DefenseJuryEvaluation::where('defense_schedule_id', $schedule->id)->avg('grade'), 2);

        $defenses->recordResult($schedule, [
            'final_grade' => $average,
            'verdict' => 'pass',
            'remarks' => '[DÉMO] Décision finale générée par la commande de démonstration.',
        ], $admin);

        $this->step("Décision finale enregistrée ({$average}/20) — soutenance terminée");
    }

    private function step(string $message): void
    {
        $this->components->twoColumnDetail($message, '<fg=green;options=bold>OK</>');
    }
}
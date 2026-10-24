<?php

namespace App\Console\Commands;

use App\Models\DefenseSchedule;
use App\Models\Thesis;
use App\Models\ThesisArchive;
use App\Models\ThesisStatusHistory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Commande de DÉMONSTRATION / TEST (jamais en production).
 *
 * Remet un mémoire dans un état propre pour rejouer le parcours : supprime
 * l'archive, la soutenance (jury, évaluations, résultat) et repositionne le
 * statut. Les versions déposées et les commentaires sont conservés.
 */
class DemoResetThesis extends Command
{
    private const TARGETS = ['draft', 'submitted', 'under_review', 'approved'];

    protected $signature = 'suma:demo-reset
        {thesis : Identifiant du mémoire}
        {--to=submitted : Statut cible (draft, submitted, under_review, approved)}
        {--yes : Ne pas demander de confirmation}';

    protected $description = 'Remet un mémoire de test dans un état propre (supprime archive et soutenance) — jamais en production';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->components->error('Commande interdite en production.');

            return self::FAILURE;
        }

        $target = (string) $this->option('to');

        if (!in_array($target, self::TARGETS, true)) {
            $this->components->error('--to doit valoir : ' . implode(', ', self::TARGETS));

            return self::FAILURE;
        }

        $thesis = Thesis::with('student.user')->find($this->argument('thesis'));

        if (!$thesis) {
            $this->components->error('Mémoire introuvable.');

            return self::FAILURE;
        }

        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'administrator'))
            ->where('is_active', true)
            ->first();

        if (!$admin) {
            $this->components->error('Aucun administrateur actif trouvé.');

            return self::FAILURE;
        }

        $archive = ThesisArchive::where('thesis_id', $thesis->id)->first();
        $schedule = DefenseSchedule::where('thesis_id', $thesis->id)->first();

        $this->components->twoColumnDetail('Mémoire', "#{$thesis->id} — {$thesis->title}");
        $this->components->twoColumnDetail('Statut actuel → cible', "{$thesis->status} → {$target}");
        $this->components->twoColumnDetail('Archive à supprimer', $archive ? 'oui' : 'non');
        $this->components->twoColumnDetail('Soutenance à supprimer (jury, évaluations, résultat)', $schedule ? 'oui' : 'non');
        $this->components->twoColumnDetail('Versions et commentaires', 'conservés');

        if (!$this->option('yes') && !$this->confirm('Confirmer la réinitialisation de ce mémoire de test ?', false)) {
            $this->components->info('Annulé.');

            return self::SUCCESS;
        }

        $previousStatus = $thesis->status;

        DB::transaction(function () use ($thesis, $archive, $schedule, $target, $previousStatus, $admin) {
            $archive?->delete();
            $schedule?->delete();

            $thesis->update([
                'status' => $target,
                'submitted_at' => $target === 'draft' ? null : ($thesis->submitted_at ?? now()),
                'approved_at' => $target === 'approved' ? now() : null,
            ]);

            ThesisStatusHistory::create([
                'thesis_id' => $thesis->id,
                'from_status' => $previousStatus,
                'to_status' => $target,
                'changed_by' => $admin->id,
                'remarks' => '[DÉMO] Réinitialisation du mémoire de test.',
                'created_at' => now(),
            ]);
        });

        $this->components->info("Mémoire #{$thesis->id} remis au statut « {$target} ».");

        return self::SUCCESS;
    }
}
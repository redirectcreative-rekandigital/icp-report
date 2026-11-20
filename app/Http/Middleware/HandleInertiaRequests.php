<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ClassController;
use App\Models\Classroom;
use App\Models\Term;
use App\Support\PinSession;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private Settings $settings) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $verified = PinSession::isVerified($request);

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
            ],
            'school' => fn () => [
                'name' => $this->settings->get('school_name'),
                'program' => $this->settings->get('program_name'),
                'logoUrl' => $this->settings->get('school_logo_path')
                    ? route('media.logo', ['slot' => 'school', 'v' => substr(md5((string) $this->settings->get('school_logo_path')), 0, 8)])
                    : null,
            ],
            'session' => fn () => $verified ? [
                'idleMinutes' => $this->settings->idleMinutes(),
            ] : null,
            'activeTerm' => fn () => $verified ? $this->activeTerm() : null,
            // Sidebar "Kelas" sub menu (PRD-08 §2.2); refresh with router.reload({ only: ['nav'] }).
            'nav' => fn () => $verified ? [
                'classes' => $this->navClasses(),
                'showDocuments' => (bool) $this->settings->get('show_documents_menu', true),
            ] : null,
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'pinConfirmRequired' => (bool) $request->session()->get('pin_confirm_required'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    private ?Term $term = null;

    private bool $termLoaded = false;

    private function term(): ?Term
    {
        if (! $this->termLoaded) {
            $this->term = Term::active();
            $this->termLoaded = true;
        }

        return $this->term;
    }

    /** @return array{id: int, label: string, short: string}|null */
    private function activeTerm(): ?array
    {
        $term = $this->term();

        return $term ? ['id' => $term->id, 'label' => $term->fullLabel(), 'short' => $term->shortLabel()] : null;
    }

    /** @return list<array<string, mixed>> */
    private function navClasses(): array
    {
        $term = $this->term();

        if (! $term) {
            return [];
        }

        return ClassController::withProgress($term->classrooms()->getQuery())
            ->orderBy('grade')->orderBy('name')
            ->get()
            ->map(fn (Classroom $c) => [
                'id' => $c->id,
                'grade' => $c->grade,
                'name' => $c->name,
                'status' => $c->status,
                'complete' => $c->complete_count,
                'total' => $c->enrollments_count,
                'englishTeacher' => $c->english_teacher,
            ])
            ->all();
    }
}

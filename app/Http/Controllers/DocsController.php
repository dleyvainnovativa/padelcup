<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\View;

/**
 * Public documentation / tutorials at /docs.
 *
 * To ADD a new doc page:
 *   1. Create a Blade partial at  resources/views/docs/articles/<slug>.blade.php
 *      containing only the article body (h2/p/ol/img — no @extends).
 *   2. Add one entry to the $docs array below.
 * That's it — routing, sidebar nav, prev/next, and the shell are automatic.
 */
class DocsController extends Controller
{
    /**
     * The docs registry, grouped into sections for the sidebar.
     * Order here is the order shown (and drives prev/next).
     *
     * slug   => URL segment + partial filename
     * title  => sidebar + page heading
     * blurb  => short description (index cards + <meta>)
     * icon   => Font Awesome class for the index card
     */
    private function sections(): array
    {
        return [
            [
                'title' => 'Primeros pasos',
                'items' => [
                    [
                        'slug' => 'primer-torneo',
                        'title' => 'Crea tu primer torneo',
                        'blurb' => 'Da de alta un torneo, define fechas y sedes en minutos.',
                        'icon' => 'fa-flag-checkered',
                    ],
                    [
                        'slug' => 'categorias-inscripciones',
                        'title' => 'Categorías, inscripciones y parejas',
                        'blurb' => 'Crea categorías, abre inscripciones e importa parejas.',
                        'icon' => 'fa-people-group',
                    ],
                ],
            ],
            [
                'title' => 'Durante el torneo',
                'items' => [
                    [
                        'slug' => 'grupos-y-llaves',
                        'title' => 'Genera grupos y llaves',
                        'blurb' => 'Arma la fase de grupos y el cuadro de eliminación con un clic.',
                        'icon' => 'fa-sitemap',
                    ],
                    [
                        'slug' => 'resultados',
                        'title' => 'Captura resultados',
                        'blurb' => 'Registra marcadores y sigue los standings en vivo.',
                        'icon' => 'fa-square-poll-vertical',
                    ],
                ],
            ],
            [
                'title' => 'Compartir y cobrar',
                'items' => [
                    [
                        'slug' => 'pagina-publica',
                        'title' => 'Página pública y cobros',
                        'blurb' => 'Comparte tu torneo y recibe inscripciones en línea.',
                        'icon' => 'fa-globe',
                    ],
                ],
            ],
        ];
    }

    /** Flatten sections into an ordered list of items (for lookup + prev/next). */
    private function flat(): array
    {
        $out = [];
        foreach ($this->sections() as $section) {
            foreach ($section['items'] as $item) {
                $out[$item['slug']] = $item;
            }
        }
        return $out;
    }

    /** Docs home: overview + cards for every article. */
    public function index()
    {
        return view('docs.index', [
            'sections' => $this->sections(),
        ]);
    }

    /** A single doc page, resolved by slug. */
    public function show(string $slug)
    {
        $flat = $this->flat();
        abort_unless(isset($flat[$slug]), 404);

        $partial = 'docs.articles.' . $slug;
        abort_unless(View::exists($partial), 404);

        // Prev / next within the flat ordering.
        $slugs = array_keys($flat);
        $i = array_search($slug, $slugs, true);
        $prev = $i > 0 ? $flat[$slugs[$i - 1]] : null;
        $next = $i < count($slugs) - 1 ? $flat[$slugs[$i + 1]] : null;

        return view('docs.show', [
            'sections' => $this->sections(),
            'current' => $flat[$slug],
            'partial' => $partial,
            'prev' => $prev,
            'next' => $next,
        ]);
    }
}

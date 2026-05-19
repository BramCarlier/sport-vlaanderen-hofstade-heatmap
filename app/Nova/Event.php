<?php

namespace App\Nova;

use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Event extends Resource
{
    /**
     * @var class-string<\App\Models\Event>
     */
    public static $model = \App\Models\Event::class;

    public static $title = 'name';

    public static $search = [
        'id',
        'name',
        'notes',
    ];

    /**
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Open Heatmap', function () {
                return '<a class="link-default font-bold" href="'.route('heatmap.index').'" target="_blank" rel="noopener">Open Heatmap</a>';
            })->asHtml()->onlyOnIndex(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Number::make('Latitude')
                ->step(0.000001)
                ->sortable()
                ->rules('required', 'numeric', 'between:-90,90'),

            Number::make('Longitude')
                ->step(0.000001)
                ->sortable()
                ->rules('required', 'numeric', 'between:-180,180'),

            Number::make('Weight')
                ->min(1)
                ->max(10)
                ->step(1)
                ->default(1)
                ->sortable()
                ->rules('required', 'integer', 'min:1', 'max:10'),

            Textarea::make('Notes')
                ->nullable()
                ->hideFromIndex()
                ->rules('nullable', 'max:5000'),

            DateTime::make('Created At')
                ->exceptOnForms()
                ->sortable(),
        ];
    }

    /** @return array<int, \Laravel\Nova\Card> */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /** @return array<int, \Laravel\Nova\Filters\Filter> */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /** @return array<int, \Laravel\Nova\Lenses\Lens> */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /** @return array<int, \Laravel\Nova\Actions\Action> */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}

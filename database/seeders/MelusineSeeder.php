<?php

namespace Database\Seeders;

use App\Models\Plotline;
use App\Models\Project;
use App\Models\User;
use App\Support\PlotlineColors;
use Illuminate\Database\Seeder;

class MelusineSeeder extends Seeder
{
    /**
     * Seed a sample "Roman of Melusine" project with plotlines and events.
     */
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'The Roman of Melusine',
            'description' => 'A medieval legend of the faerie Melusine, her curse, her marriage to Raymondin of Lusignan, and the fates of their nine sons.',
        ]);

        $mainPlotline = $project->plotlines()->firstWhere('is_main', true)
            ?? Plotline::create([
                'project_id' => $project->id,
                'name' => 'Main plotline',
                'is_main' => true,
                'color' => PlotlineColors::PRESETS[0], // red-500
            ]);

        $curseOfPressine = Plotline::create([
            'project_id' => $project->id,
            'name' => 'The Curse of Pressine',
            'description' => "Pressine's marriage to Count Elinas, the broken oath, and the curse she lays on her daughters.",
            'color' => PlotlineColors::PRESETS[8], // green-500
        ]);

        $melusineAndRaymondin = Plotline::create([
            'project_id' => $project->id,
            'name' => 'Melusine & Raymondin',
            'description' => 'The courtship, marriage, and eventual undoing of Melusine and Raymondin of Lusignan.',
            'color' => PlotlineColors::PRESETS[16], // sky-500
        ]);

        $sonsOfLusignan = Plotline::create([
            'project_id' => $project->id,
            'name' => 'The Sons of Lusignan',
            'description' => "The conquests, triumphs, and tragedies of Melusine and Raymondin's nine sons.",
            'color' => PlotlineColors::PRESETS[24], // purple-500
        ]);

        $events = [
            [
                'title' => 'The Oath at the Fountain',
                'description' => 'Count Elinas meets Pressine at a forest fountain and swears never to look upon her in childbirth.',
                'event_datetime' => '1000-03-01 12:00:00',
                'plotlines' => [$curseOfPressine, $mainPlotline],
            ],
            [
                'title' => 'The Broken Oath',
                'description' => 'Elinas peers through the keyhole and sees Pressine bathing their infant daughters. Pressine flees to Avalon.',
                'event_datetime' => '1001-01-10 09:00:00',
                'plotlines' => [$curseOfPressine],
            ],
            [
                'title' => 'The Vengeance',
                'description' => 'Melusine, Melior, and Palatine seal Count Elinas alive within the Branded Mountain.',
                'event_datetime' => '1016-06-15 22:00:00',
                'plotlines' => [$curseOfPressine],
            ],
            [
                'title' => 'The Second Curse',
                'description' => "Pressine curses Melusine to become a serpent from the waist down every Saturday.",
                'event_datetime' => '1016-06-20 08:00:00',
                'plotlines' => [$curseOfPressine, $melusineAndRaymondin],
            ],
            [
                'title' => 'The Accidental Blow',
                'description' => 'Hunting a boar, Raymondin accidentally kills his uncle, the Count of Poitiers, with a stray spear.',
                'event_datetime' => '1035-09-02 07:30:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'The Meeting at the Fountain of Thirst',
                'description' => 'Raymondin flees into the forest and meets Melusine, who offers him wealth and marriage in exchange for his secrecy.',
                'event_datetime' => '1035-09-03 20:00:00',
                'plotlines' => [$melusineAndRaymondin],
            ],
            [
                'title' => 'The Building of Lusignan',
                'description' => 'In a single night, Melusine raises the Castle of Lusignan from a thorned promontory.',
                'event_datetime' => '1035-10-01 05:00:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'The Birth of the Eight Sons',
                'description' => 'Melusine bears Raymondin eight sons, each marked by her faerie blood, and secretly hides a ninth, Horrible, in the cellar.',
                'event_datetime' => '1042-04-12 03:00:00',
                'plotlines' => [$melusineAndRaymondin, $sonsOfLusignan],
            ],
            [
                'title' => 'The Great Conquests',
                'description' => 'Urien, Guyon, Antoine, and Reynaud each depart in the same month to win crowns and titles across Europe and the East.',
                'event_datetime' => '1060-05-01 06:00:00',
                'plotlines' => [$sonsOfLusignan],
            ],
            [
                'title' => 'The Burning of Malliers',
                'description' => 'Geoffroy burns the abbey of Malliers in a fit of rage, killing his gentle brother Fromont, who was inside at prayer.',
                'event_datetime' => '1061-11-30 23:00:00',
                'plotlines' => [$sonsOfLusignan],
            ],
            [
                'title' => 'The Keyhole',
                'description' => "Consumed by doubt, Raymondin looks through the keyhole of Melusine's chamber and sees her serpent form.",
                'event_datetime' => '1061-12-07 21:00:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'The Transformation',
                'description' => 'After Raymondin denounces her before the whole court, Melusine transforms into a winged serpent and flies from Lusignan forever.',
                'event_datetime' => '1061-12-14 19:00:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'The Fall of Lusignan',
                'description' => 'Generations later, the line of Lusignan fades and the castle crumbles to ruin, though Melusine is still said to circle its towers.',
                'event_datetime' => '1200-01-01 00:00:00',
                'plotlines' => [$mainPlotline],
            ],
        ];

        foreach ($events as $eventData) {
            $plotlines = $eventData['plotlines'];
            unset($eventData['plotlines']);

            $event = $project->events()->create($eventData);
            $event->plotlines()->attach(collect($plotlines)->pluck('id'));
        }
    }
}

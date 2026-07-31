<?php

namespace Database\Seeders;

use App\Enums\BookLanguage;
use App\Enums\CodexEntryType;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\User;
use App\Services\AttributeTimeline;
use App\Support\PlotlineColors;
use Database\Seeders\Concerns\BackfillsSceneWordCounts;
use Illuminate\Database\Seeder;

class MelusineSeederEn extends Seeder
{
    use BackfillsSceneWordCounts;

    /**
     * Seed a sample "Roman of Melusine" project (English) with plotlines and events.
     */
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        // Guards against re-running `db:seed` against a database that already has
        // this demo project — without it, every re-run (e.g. `make seed` invoked
        // twice) duplicates the whole project tree instead of no-op'ing.
        if ($user->projects()->where('name', 'The Roman of Melusine')->exists()) {
            return;
        }

        // Rich-HTML description: showcases the new format (a heading + a list) so the
        // Story overview and detail pages render real markup. Every string is within the
        // sanitizer allow-list; the set-mutator on Project::description cleans it on write
        // regardless (see App\Models\Concerns\SanitizesRichHtml).
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'The Roman of Melusine',
            'language' => BookLanguage::English,
            'description' => <<<'HTML'
                <p>A medieval legend of the faerie <strong>Melusine</strong>, her curse, her marriage to <em>Raymondin of Lusignan</em>, and the fates of their nine sons.</p>
                <h3>The threads of the tale</h3>
                <ul>
                    <li>The curse Pressine lays upon her daughters.</li>
                    <li>The bargain and marriage of Melusine and Raymondin.</li>
                    <li>The conquests and tragedies of the sons of Lusignan.</li>
                </ul>
                HTML,
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
            'description' => "<p>Pressine's marriage to Count Elinas, the <strong>broken oath</strong>, and the curse she lays on her daughters.</p>",
            'color' => PlotlineColors::PRESETS[8], // green-500
        ]);

        $melusineAndRaymondin = Plotline::create([
            'project_id' => $project->id,
            'name' => 'Melusine & Raymondin',
            'description' => '<p>The courtship, marriage, and eventual <em>undoing</em> of Melusine and Raymondin of Lusignan.</p>',
            'color' => PlotlineColors::PRESETS[16], // sky-500
        ]);

        $sonsOfLusignan = Plotline::create([
            'project_id' => $project->id,
            'name' => 'The Sons of Lusignan',
            'description' => "<p>The conquests, triumphs, and tragedies of Melusine and Raymondin's <strong>nine sons</strong>.</p>",
            'color' => PlotlineColors::PRESETS[24], // purple-500
        ]);

        foreach ([
            ['title' => 'Start', 'event_datetime' => '0001-01-01 00:00:00'],
            ['title' => 'End', 'event_datetime' => '3000-01-01 00:00:00'],
        ] as $bookend) {
            $bookendEvent = $project->events()->firstOrCreate(
                ['title' => $bookend['title']],
                $bookend + ['is_fixed' => true],
            );
            $bookendEvent->plotlines()->syncWithoutDetaching($mainPlotline->id);
        }

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
                'description' => 'Pressine curses Melusine to become a serpent from the waist down every Saturday.',
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

        $eventsByTitle = [];

        foreach ($events as $eventData) {
            $plotlines = $eventData['plotlines'];
            unset($eventData['plotlines']);

            $event = $project->events()->create($eventData);
            $event->plotlines()->attach(collect($plotlines)->pluck('id'));

            $eventsByTitle[$event->title] = $event;
        }

        // The timeline event each scene depicts ("happens during"), keyed by scene name.
        // Scenes with no matching event are intentionally left unassigned (flagged in the UI).
        $sceneEvents = [
            'A Lady at the Fountain' => 'The Oath at the Fountain',
            'Through the Keyhole' => 'The Broken Oath',
            'The Branded Mountain' => 'The Vengeance',
            "Pressine's Judgment" => 'The Second Curse',
            'The Boar Hunt' => 'The Accidental Blow',
            'A Meeting in the Clearing' => 'The Meeting at the Fountain of Thirst',
            'The Vow' => 'The Meeting at the Fountain of Thirst',
            'A Castle Raised in a Night' => 'The Building of Lusignan',
            'Lord and Lady of Lusignan' => 'The Building of Lusignan',
            'Eight Marked Sons' => 'The Birth of the Eight Sons',
            'The Cellar Child' => 'The Birth of the Eight Sons',
            'Four Ships Leave Poitou' => 'The Great Conquests',
            'Melusine at the Spinning Wheel' => 'The Great Conquests',
            "Geoffroy's Fire" => 'The Burning of Malliers',
            'Breaking the Cage' => 'The Burning of Malliers',
            'Two Messengers' => 'The Burning of Malliers',
            'What Raymondin Saw' => 'The Keyhole',
            'A Week of Silence' => 'The Keyhole',
            'The Denouncement' => 'The Transformation',
            'A Flower Opening' => 'The Transformation',
            'Around the Towers' => 'The Transformation',
            'Bound by Memory' => 'The Fall of Lusignan',
            'A Heap of Broken Stone' => 'The Fall of Lusignan',
            'Until the Next Lord Comes Home' => 'The Fall of Lusignan',
        ];

        $acts = [
            [
                'name' => "Melusine's Youth",
                'description' => "<p>Pressine's marriage to Count Elinas, the broken oath, and the curse she lays on her daughters — culminating in the curse that will shape Melusine's own life.</p>",
                'chapters' => [
                    [
                        'name' => "Pressine's Curse",
                        'description' => "Count Elinas breaks his oath to Pressine, their daughters take vengeance, and Pressine lays the curse that will shape Melusine's own life.",
                        'scenes' => [
                            [
                                'name' => 'A Lady at the Fountain',
                                'contents' => "The forest of Brittany stretched dense and ancient, its oaks so broad that their branches wove a canopy through which only threads of sunlight reached the mossy floor. Count **Elinas of Albany**, separated from his hunting party, pushed through ferns and brambles, his horse picking a path between lichen-crusted stones. The air smelled of damp earth and wild garlic.\n\nDeep in the greenwood he came upon a clearing unlike any he had seen. At its centre stood a fountain of white marble, veined with green, its basin worn smooth by centuries. Three streams fed it, and the water was so clear that the pebbles at the bottom lay visible as if through glass. Beside the fountain sat a lady, drawing a comb of gold through hair that fell about her like spun shadow. Her gown was the colour of moonlit water.\n\nHer name was **Pressine**, and she was of the faerie kind.\n\nElinas dismounted, his breath caught. \"Lady,\" he said, \"I have never seen one so fair. Will you be my wife?\"\n\nPressine lifted her gaze. Her eyes were the colour of deep water — not blue, not grey, but something between, fathomless. \"I will, my lord, on one condition: you must never look upon me when I give birth to our children. Swear it.\"\n\nElinas knelt on the moss beside the fountain and swore by the bones of his fathers.",
                            ],
                            [
                                'name' => 'Through the Keyhole',
                                'contents' => "In the great stone keep of Alban, in a chamber hung with tapestries of the hunt, Pressine bore him **three daughters** in a single birth — Melusine, Melior, and Palatine. The midwives had barred the door and drawn the curtains close, and within the room the only light came from a single iron candelabra whose flames cast wavering shadows across the walls.\n\nElinas paced the corridor. The cries of the newborn reached him through the oak — thin, piercing sounds that wove into his chest. He grew anxious for his wife. He pressed his ear to the wood and heard the splash of water, the low murmur of the midwives' voices.\n\nHe crept to the door and peered through the keyhole.\n\nThe aperture showed him a sliver of the chamber: a silver bath, wide and deep, catching the candlelight in ripples. Pressine sat within it, her hair loose and dark against her white shoulders, washing the blood from her infant daughters. The water was tinged pink. The babies' faces were small and wrinkled and perfect.\n\nThen her eyes met his through the crack.\n\n\"Foolish man,\" she wept, and her voice carried the weight of a door closing forever. \"You have broken your oath. Now I must leave you, and our daughters shall carry the weight of your betrayal.\"\n\nBy the time Elinas forced the door open, the chamber was empty. The silver bath stood cold. The tapestries hung still. Pressine and the three infants had vanished to the hidden island of **Avalon**, where no mortal man could follow.",
                            ],
                            [
                                'name' => 'The Branded Mountain',
                                'contents' => "Years passed on Avalon, where the mist never fully lifted and the sun rose thin and silver through the haze. The island was ringed by cliffs of white chalk, and the only sound was the endless hush of the sea. There Pressine raised her daughters in the old arts — the songs of the wind, the language of stone, and the weaving of fate.\n\nMelusine grew tall and beautiful, with hair black as the ravens that nested in the island's towers, and a pride that burned steady as a candle flame. When she learned how her father had broken his vow and driven her mother away, fury kindled in her heart.\n\n\"He must pay,\" she told Melior and Palatine.\n\nThat night the three sisters crossed the sea in a boat with no sail, guided by no star. They found Count Elinas sleeping in the great hall of Alban, his grey head resting on a table littered with the bones of supper, the hearth-fire sunk to embers. They bound him in chains of faerie silver — cold, endlessly cold — and dragged him through the forest to the mountain that the locals called the Branded Mountain, a peak of black basalt scarred by lightning strikes.\n\nThey **sealed him alive** within it, in a crevice so narrow that the rocks pressed against his ribs. No light reaches that place. No sound penetrates the stone. There he remains, breathing rock, dreaming of the wife he lost.",
                            ],
                            [
                                'name' => "Pressine's Judgment",
                                'contents' => "Pressine discovered what her daughters had done. She called them to the chamber at the heart of Avalon — a round room of pale stone where the only furniture was an obsidian table and three chairs of carved oak. Through the high windows, the sea glimmered like hammered pewter. The air was cold and still.\n\nWhen the three sisters entered, Pressine was standing with her back to them, her silver hair unbound, her hands resting on the table's edge. She turned. Her face, usually warm, had become cold as winter water.\n\n\"You have broken the sacred law,\" she said. \"A child must not raise a hand against a parent. For this, Melusine, you shall bear the heaviest curse.\"\n\nMelusine sank to her knees on the cold stone floor. \"Mother, I only sought justice for you.\"\n\n\"This is your justice. **Every Saturday**, from your waist down, you shall become a serpent. If a mortal man marries you and never sees you on a Saturday, you may live as a woman and die as a woman. But if he sees, and if he speaks of it to another — you shall become a winged serpent forever, condemned to wander the earth until the Last Judgment.\"\n\n\"And my sisters?\"\n\nPressine's gaze moved to Melior and Palatine, who stood pressed together by the door. \"Melior shall be locked in a tower until a knight brave enough to serve her appears. Palatine shall guard the treasure of the mountain until a worthy hero claims her.\"\n\nMelusine rose from her knees, her hands clenched at her sides. \"Then I shall find a mortal man who will keep my secret. And I shall bear sons who will shake the world.\"",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Melusine & Raymondin',
                'description' => '<p>Raymondin accidentally kills his uncle, pledges himself to Melusine, and together they raise Lusignan, marry, and bear nine sons — until his broken oath drives her from the castle forever.</p>',
                'chapters' => [
                    [
                        'name' => 'The Meeting and the Vow',
                        'description' => 'Raymondin kills his uncle by accident, meets Melusine at a forest fountain, and swears to honor her one condition — while she raises him a castle in a single night.',
                        'scenes' => [
                            [
                                'name' => 'The Boar Hunt',
                                'contents' => "The forest of Poitou was a tangle of oak and hornbeam, the ground thick with leaf-mould and the roots of ancient trees. A cold autumn light filtered through the thinning canopy. A young knight named **Raymondin** rode beside his uncle, the Count of Poitiers, following the tracks of a great boar through the mud. Their horses' breath plumed in the chill air.\n\nThe beast broke cover without warning, crashing through the underbrush — a massive tusked creature with bristles black as pitch and small, furious eyes. The Count spurred his horse forward, lance lowered.\n\n\"Now, nephew! Strike!\"\n\nRaymondin hurled his spear. The boar swerved at the last instant. The spear, meant for the beast's flank, struck the Count through the ribs instead, punching through the links of his hauberk and piercing his heart.\n\nThe old man made a sound like a sigh. He fell from his horse and did not move, his cloak spreading on the dead leaves like a pool of blood.\n\nRaymondin slid from his saddle and knelt beside him, his hands red. \"Uncle — I did not mean — the boar —\"\n\nBut the dead cannot hear.",
                            ],
                            [
                                'name' => 'A Meeting in the Clearing',
                                'contents' => "Raymondin fled into the forest, his thoughts scattered like startled birds. He rode until his horse collapsed, its flanks heaving, then walked until his boots wore through and the leather peeled from his soles. The trees darkened around him as evening fell, and the cries of jays gave way to the hooting of owls.\n\nAt last, half-blind with exhaustion, he came to a clearing where three streams converged to feed a fountain of white marble, its basin carved with figures he could not quite make out in the twilight. He fell to his knees on the moss and drank, the water so cold it ached in his throat.\n\n\"Your sorrow is deep, knight.\"\n\nThe voice was low and musical, like water running over stone. He looked up.\n\nA woman stood before him in a gown of silver cloth that caught the last light of the dying day. Her hair fell to her waist, black as a raven's wing, and her face was the most beautiful he had ever seen — high cheekbones, a mouth that seemed to know sorrow, and eyes the colour of sherry in candlelight.\n\n\"I am **Melusine**,\" she said. \"I know what you have done. It was the boar's fault, not yours — the beast deceived your aim.\"\n\n\"How can you know this?\" His voice cracked.\n\n\"I know many things. I know that if you return to Poitiers, the Count's men will call you murderer and hang you from the walls. I know the colour of your guilt, and I know it does not belong to you.\"\n\nRaymondin wept, his shoulders shaking. \"Then I am already dead.\"\n\n\"You are not. I can give you lands, a castle, wealth beyond counting. I can make you the greatest lord in Poitou — if you will take me as your wife.\"\n\nHe stared at her. \"You would marry a man with blood on his hands?\"\n\n\"I would marry a man with a true heart who made a terrible mistake.\" She extended her hand, pale in the twilight. \"Do not let one moment decide the rest of your life.\"",
                            ],
                            [
                                'name' => 'The Vow',
                                'contents' => "The clearing had grown dark around them. A sliver of moon climbed above the trees, and the fountain caught its light, each ripple edged in silver. Raymondin looked at Melusine's face and saw no mockery, no deceit — only a stillness like the surface of deep water.\n\n\"I will marry you,\" he said.\n\nMelusine smiled, but the smile carried something grave beneath it. \"Then you must swear one thing. **Every Saturday**, I must be alone. I must lock myself in my chamber, and no one — not even you — may look upon me. Not through the door, not through the window, not through any crack or gap. Swear this, and I will give you everything.\"\n\n\"I swear it on my soul,\" said Raymondin, and the words hung in the cold night air like a bell's last vibration.\n\n\"Then come,\" she said, taking his hand. Her palm was warm against his. \"Let me show you where we shall live.\"",
                            ],
                            [
                                'name' => 'A Castle Raised in a Night',
                                'contents' => "Melusine led Raymondin to a wild promontory overlooking the river — a jagged spine of rock covered in thorns and brambles, where the wind tore at their clothes and the water churned far below. The place felt ancient, untouched by any hand.\n\n\"Here,\" she said, her voice carrying over the wind, \"I will build you a castle.\"\n\nThat night, Raymondin slept under an oak at the edge of the wood, wrapped in his cloak. Melusine walked alone to the promontory and raised her arms to the moon. She summoned **powers older than the cross** — the spirits of the earth, the whisperers in the stone, the things that lived in the dark before the first church was built.\n\nAll night the ground trembled. Raymondin stirred in his sleep but did not wake. Blocks of white marble, veined with grey, rose from the soil like the fingers of a buried giant. Towers pushed upward, stones fitting themselves together without mortar. Walls wove themselves from the living rock, each course finding its neighbour as naturally as bones in a hand. Windows opened like eyes. The great hall's roof arched into place, each ribbed vault settling with a groan.\n\nBy dawn, the **Castle of Lusignan** stood complete — gates of iron-bound oak, battlements against the sky, a chapel with a rose window, and a keep so tall it seemed to scratch the clouds.\n\nRaymondin woke to the smell of fresh stone and woodsmoke. He sat up, rubbed his eyes, and saw it. He fell to his knees.\n\n\"It is a miracle,\" he whispered.\n\n\"It is a home,\" said Melusine.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Sons of Lusignan',
                        'description' => 'Melusine weds Raymondin, bears eight marked sons and a hidden ninth, and watches four of them win crowns and titles across Europe and the East.',
                        'scenes' => [
                            [
                                'name' => 'Lord and Lady of Lusignan',
                                'contents' => "The wedding was held in the castle chapel, a vaulted space of pale stone where the morning light fell through the rose window in shards of ruby and sapphire. The Bishop of Poitiers presided in cloth of gold, and the incense curled upward like prayers. Melusine wore a gown of white silk stitched with pearls, and her beauty was spoken of in every hall in France.\n\nThe feast that followed filled the great hall with the smell of roasted meat and mulled wine, and Melusine's generosity knew no bounds — she gave rich gifts to every guest, jewelled cups and lengths of velvet, and none left hungry. Raymondin was proclaimed **Lord of Lusignan** before the assembled nobility of Poitou.\n\nBut every Saturday, as the bells of the chapel tolled None, Melusine withdrew to her tower chamber. She barred the door with iron and spoke to no one. The servants exchanged glances in the corridors. The kitchen maids crossed themselves when they passed her door. Raymondin said nothing.",
                            ],
                            [
                                'name' => 'Eight Marked Sons',
                                'contents' => "Year by year, in the great carved bed of the tower chamber hung with curtains of crimson velvet, Melusine bore Raymondin **eight sons**. Each came into the world with a strange mark — a sign of his mother's faerie blood — and each birth was attended by the same hush among the midwives, the same whispered crossing of themselves as they beheld the infant's peculiarity.\n\n**Urien**, the firstborn, had a single red ear — the ear of a wolf. He grew swift and bold, and would become a king in Cyprus.\n\n**Guyon**, the second, had a left eye that gleamed like a cat's in darkness. He was quiet and watchful, and would slay a giant in Luxembourg.\n\n**Antoine**, the third, bore a claw-mark on his cheek where his mother's nail had grazed him in the night. He would conquer half of Armenia.\n\n**Reynaud**, the fourth, had one eye set higher than the other. He was sharp and calculating, and would become Constable of France.\n\n**Geoffroy**, the fifth — called Geoffroy of the Big Tooth — had a tusk of bone jutting from his jaw. His temper was a furnace that nothing could quench. He would burn an abbey and kill his own brother.\n\n**Fromont**, the sixth, bore a red mark across his nose like a monk's hood. He alone was gentle. He turned from swords to scripture, entered the Church, and prayed for his family's sins.\n\n**Thierry**, the seventh, had one eye red as blood. He would become Lord of Vouvant.\n\n**Raymondet**, the eighth and last, had three eyes — two in the usual place and one above his nose. His mother loved him most of all.",
                            ],
                            [
                                'name' => 'The Cellar Child',
                                'contents' => "But there was a **ninth son**, born in secret, whom Melusine hid far below the castle. The deepest cellar of Lusignan was a place of damp stone and absolute blackness, where the only sounds were the drip of water and the scurry of rats. A rusted iron gate sealed the entrance, and the steps down were worn slick by centuries.\n\nThere she placed him. His name was **Horrible**.\n\nHe bore no mark on his face — his entire body was the mark. His skin was scaled like a serpent's, pale and smooth. His fingers ended in claws black as jet. His teeth were needles. He grew in the dark, feeding on raw meat that Melusine brought him in a covered basket, speaking only in hisses.\n\nShe visited him in the night, a single candle casting her shadow enormous on the cellar walls. \"You are my son,\" she whispered, crouching before his cage, \"but you cannot walk among men. You would terrify them.\"\n\nHorrible gnawed a bone and said nothing, his yellow eyes fixed on her face.",
                            ],
                            [
                                'name' => 'Four Ships Leave Poitou',
                                'contents' => "In the same month, **four ships** left the harbour of Poitou, their sails filling with the same wind. The harbour was a chaos of merchants and fishermen, gulls crying overhead, the salt spray flying over the stone quays.\n\n**Urien** sailed east to the island of Cyprus. The Saracens held the coast, their galleys darkening the horizon, but Urien burned their fleet and broke their lines in a single morning. The King of Cyprus gave him his daughter in gratitude, and Urien wore a crown.\n\n**Guyon** rode north through the forests to Luxembourg. A giant named Maldichet had been devouring children from the mountain villages, and the roads were empty of travellers. Guyon met him on a stone bridge over the river Sure. They fought for three hours. The giant crushed Guyon's shield to splinters; Guyon cut his hamstrings, then drove his sword up under the ribs. The Duke of Luxembourg knighted him on the battlefield, his armour still wet with the giant's blood.\n\n**Antoine** sailed east to Armenia. The Sultan had besieged the capital for seven years, and the walls were cracked and weary. Antoine rode out alone before the gates and challenged the Sultan's champion. One stroke — from saddle to chin. The Armenian King offered half his kingdom and his daughter's hand.\n\n**Reynaud** rode to Paris, to the court of the King of France, where the halls were hung with tapestries and the floors were strewn with rushes. He joined the royal army, fought in three campaigns, and never lost a skirmish. The King made him Constable of France — the highest knight in the realm, commander of the armies.",
                            ],
                            [
                                'name' => 'Melusine at the Spinning Wheel',
                                'contents' => "While all this unfolded, Melusine sat alone in her tower at Lusignan, the highest room in the keep, where the windows faced the four directions. The room was spare: a wooden chair, a spinning wheel of dark oak, and a chest of linens. A fire crackled in the stone hearth, and the only other light came from a single beeswax candle on the windowsill.\n\nShe spun thread of silver, the wheel humming a steady rhythm. In the twisting filaments she glimpsed each of her sons, scattered across the world — Urien on his throne, Guyon standing over the giant's corpse, Antoine wiping his sword clean, Reynaud kneeling before the King of France. The thread carried them to her like a song carried on a distant wind.\n\n\"Four are safe,\" she murmured, her hand stilling on the wheel. \"But the fifth —\"\n\nShe saw **fire**. Flames rising against a night sky, a spire collapsing, a monk's cowl burning on a familiar face.\n\nThe thread snapped in her fingers.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Poison and the Keyhole',
                        'description' => "Geoffroy's rage burns an abbey and kills his brother, Horrible escapes the cellar, and doubt sown by the Count of Forez drives Raymondin to spy on his wife.",
                        'scenes' => [
                            [
                                'name' => "Geoffroy's Fire",
                                'contents' => "The Abbey of Malliers stood in a valley of oak and ash, its stone walls grey with age, its chapel spire rising above the treeline like a finger pointing to heaven. Geoffroy had stayed in Poitou while his brothers sailed the world, and his temper swelled with his strength. When the Abbot of Malliers refused him passage through the abbey's hunting grounds — a stretch of forest the monks had held for centuries — his blood caught flame.\n\n\"Deny me?\" he roared in the abbot's parlour, a room of plain stone and narrow windows where the only ornament was a wooden crucifix. \"I am a son of Lusignan. No monk will bar my way.\"\n\nThe abbot, old and grey, did not flinch. \"The law of the Church —\"\n\nBut Geoffroy was not listening.\n\nThat night he returned with torches. He set fire to the abbey roof, where the thatch was dry as tinder. The flames spread from the chapel to the dormitory to the library, and monks ran screaming into the night, their robes ablaze, their shadows leaping enormous against the burning walls.\n\nInside the abbey, visiting for prayers in the quiet of the night, was **Fromont** — Geoffroy's own brother, the gentle sixth son, who had chosen the cowl over the sword.\n\nFromont died in the flames, his monk's cowl burning on his face, his rosary beads fusing to his fingers.",
                            ],
                            [
                                'name' => 'Breaking the Cage',
                                'contents' => "In the deepest cellar of Lusignan, where the walls wept moisture and the darkness was absolute, **Horrible** felt the fire. He smelled it through the stone — smoke, burning thatch, roasting flesh. He heard the screaming in his blood, a vibration that travelled through the foundations of the castle.\n\nHe broke the iron bars of his cage. The rusted lock gave way like wet bread. He crawled up through the cellars, past wine racks and old bones, up stone steps slick with moss, and into the castle.\n\nThat night, three servants vanished from the lower halls. In the morning, only gnawed bones were found in the scullery, the marrow sucked clean.\n\nMelusine discovered Horrible in the kitchens, a vast room of cold hearths and hanging copper pots. He was crouched over a fourth body, his claws red. She stood in the doorway, the candle in her hand throwing long shadows across the floor. She offered no reproach.\n\n\"You cannot stay here,\" she said softly. \"You will destroy everything.\"\n\nShe led him to a secret tunnel beneath the castle, a narrow passage behind a false wall in the buttery, which opened into the deep woods beyond the river. \"Go north,\" she said. \"Find the mountains. Live in the caves. I will send you food.\"\n\nHorrible looked at her with yellow eyes that reflected the candlelight. \"Mother,\" he hissed — the only word he ever spoke.\n\nHe vanished into the dark.",
                            ],
                            [
                                'name' => 'Two Messengers',
                                'contents' => "The great hall of Lusignan was quiet that afternoon, the fire burning low in the cavernous hearth. Raymondin sat at the high table with his knights, the remnants of the midday meal spread before them — bread, cheese, a flagon of wine. Sunlight fell through the high windows in long dusty beams.\n\nThe first messenger arrived as the bells of the chapel were ringing None. He was mud-spattered, his horse lathered, his face pale. He knelt before the high table.\n\n\"Geoffroy has burned the abbey of Malliers,\" he said. \"Every monk is dead. And Fromont — your son **Fromont** — was among them.\"\n\nRaymondin's face went grey, the colour draining as if a cup had been overturned. \"My son killed his brother?\"\n\n\"The fire took him, my lord. Whether by Geoffroy's hand or the flames, no one knows. The abbey is ash.\"\n\nThe hall fell silent. The knights exchanged glances.\n\nThe second messenger arrived within the hour — not a courier but the Count of Forez himself, a lean man in a velvet tunic, his smile thin as a knife-blade. He had long resented Raymondin's rise, and the news of Malliers had brought him to Lusignan as surely as vultures follow battle.\n\n\"Tragic news, my lord,\" said Forez, taking a seat at the table without being invited, pouring himself wine. \"But I wonder — have you never questioned your wife's strange ways? The secret Saturdays, when she bars herself in her tower? The sudden wealth that built this castle overnight? The sons born with claws and tusks?\"\n\n\"Hold your tongue,\" said Raymondin, his voice low.\n\n\"I am only thinking of you, my friend.\" Forez raised his cup, his eyes glinting over the rim. \"**What kind of woman births monsters?**\"",
                            ],
                            [
                                'name' => 'What Raymondin Saw',
                                'contents' => "That Saturday, Raymondin could not rest. He walked the corridors of his castle like a ghost, the stones cold under his bare feet, Forez's words echoing in his skull: *What kind of woman births monsters?* The passage was dark, lit only by a single cresset burning in its iron cage at the far end. Shadows pooled in the corners.\n\nHe found himself outside Melusine's chamber. The door was oak, banded with iron, and a sliver of candlelight bled through the keyhole — a thin gold line in the darkness.\n\nHe knelt. His knees pressed into the cold stone.\n\nHe looked.\n\nInside, Melusine sat in a great marble bath filled with steaming water, the surface rippled with fragrant oils. The chamber was lit by a dozen candles set in silver holders, their flames reflecting off the white walls. From the waist up, she was the woman he had married — her white shoulders, her dark hair pinned loosely, her face calm as carved ivory. But from the waist down, her body was a **serpent** — a long, thick tail covered in scales of silver and blue that caught the candlelight like a thousand tiny mirrors, coiling and uncoiling in the water with a slow, sinuous grace.\n\nRaymondin's heart stopped. He fell backward, his hand over his mouth, his breath coming in ragged gasps.\n\nHe crept away without a sound, the image burned into his vision like after-light from a flame.",
                            ],
                            [
                                'name' => 'A Week of Silence',
                                'contents' => "For a week, Raymondin said nothing. The meals in the great hall passed in a thick silence broken only by the clatter of knives and the crackling of the fire. He sat across from Melusine and could not meet her eyes — he stared at his plate, at the wine in his cup, at the grain of the oak table. When she reached across the table and touched his hand, a tremor of fear passed through him, visible as a ripple on water.\n\nMelusine watched him across the candlelit table. She said nothing, but her eyes followed him as he left the hall, as he climbed the stairs, as he shut the door of his own chamber.\n\nShe **knew**.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Breaking',
                        'description' => "Word of Urien's death arrives, Raymondin denounces Melusine before the whole court, and she transforms into a winged serpent and flies from Lusignan forever.",
                        'scenes' => [
                            [
                                'name' => "Urien's Death",
                                'contents' => "Seven days after the fire, a third messenger arrived at Lusignan. The great hall was cold despite the fire, the torches guttering in their brackets. Raymondin sat in his carved chair at the high table, his knights gathered on the benches below, the air thick with the smell of woodsmoke and wool.\n\nThe messenger entered walking backwards, his head bowed — the posture of one bearing the worst kind of news. He knelt.\n\n**Urien** was dead in Cyprus. The Saracens had ambushed him in a mountain pass, among rocks where no cavalry could form. He had died sword in hand, covered in wounds, and had not fallen until his men were safe. They had found him still standing, propped against a boulder, his eyes open.\n\nRaymondin received the news in silence. The hall was so quiet that the fall of a log in the hearth sounded like a stone dropped into still water.",
                            ],
                            [
                                'name' => 'The Denouncement',
                                'contents' => "Raymondin rose from his chair. His face was pale as bone, his hands trembling against the oak table. The knights looked up from their cups, sensing something breaking.\n\n\"I have kept a secret too long,\" he said, his voice carrying across the hall. \"I have harboured a demon in my own house.\"\n\nThe doors at the far end of the hall swung open. Melusine entered, her gown the colour of autumn leaves, her hair bound in silver. She stopped at the threshold, seeing the faces turned toward her — the fear, the confusion.\n\n\"Raymondin —\" she said. \"Do not speak.\"\n\nBut he was already beyond hearing. \"My wife is a **serpent**!\" he shouted, pointing at her across the length of the hall. \"Every Saturday, she becomes a snake below the waist. I saw her with my own eyes through the keyhole of her chamber. Our children — these monsters — are the spawn of a faerie beast!\"\n\nA cry swept through the hall like a gust of wind. Knights crossed themselves. Ladies shrank back against the tapestried walls. The dogs beneath the tables began to whine.",
                            ],
                            [
                                'name' => 'A Flower Opening',
                                'contents' => "Melusine stood in the centre of the hall, alone in a ring of terrified faces. The firelight played across her features, and she did not weep. She did not rage. Her face held only an ancient, bottomless sorrow, older than the castle, older than any mortal grief.\n\n\"You broke your oath,\" she said. Her voice was quiet, but it carried to every corner of the hall. \"You looked. And now you have spoken.\"\n\n\"Your son killed his brother!\" Raymondin cried, his voice cracking. \"Your other son claws men apart in the dark! You are cursed, woman, and you have cursed me!\"\n\n\"I never cursed you.\" She shook her head slowly. \"I loved you. I gave you everything.\"\n\nShe spread her arms. The transformation began — not in pain, but like a **flower opening** to the morning sun. Her gown dissolved into mist, silver and grey. Wings of silver and white unfurled from her shoulders, vast and gleaming, wide enough to touch the walls on either side. Her legs fused, lengthened, became a serpent's tail covered in scales that caught the firelight. Scales of pearl and sapphire climbed her waist, her ribs, her throat, until her skin shimmered with them.\n\nShe rose into the air, her hair unbound and streaming behind her — a dragon-woman, beautiful and terrible, her eyes holding the same fathomless sorrow.\n\n\"I loved you,\" she said from above them all. \"I loved our sons. I have loved no one else in all the long years of my life.\"\n\nShe circled the great hall once, the candles guttering in the wind of her passage, then flew through the tall window, shattering the glass, and vanished into the night.",
                            ],
                            [
                                'name' => 'Around the Towers',
                                'contents' => "Outside, the night was cold and clear, the stars sharp as needles. Melusine flew **three times** around the Castle of Lusignan, her vast wings beating against the dark. Her cry was not a scream — it was a sound like a breaking harp, like ice cracking on a frozen river, a sound that travelled across the valleys and was heard in every village within a day's ride. Shepherds woke and crossed themselves. Children buried their faces in their mothers' skirts.\n\nOn the first circuit, the towers wept mortar. Cracks ran through the stone like veins, and dust sifted down into the courtyards.\n\nOn the second circuit, the gates groaned and split. The iron hinges buckled, and the great oak doors sagged open.\n\nOn the third circuit, she did not look back. She rose into the clouds, her silhouette dark against the moon, and was gone.",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'After Melusine',
                'description' => "<p>In the wake of Melusine's flight, Raymondin dies a penitent, Horrible grows wild in the mountains, and the sons of Lusignan meet their fates one by one until the line and castle pass into ruin and legend.</p>",
                'chapters' => [
                    [
                        'name' => 'The Ruin of Lusignan',
                        'description' => "Melusine's haunting, Raymondin's penitent death, Horrible's exile, the fall of the sons one by one, and the castle's slow passage into ruin and legend.",
                        'scenes' => [
                            [
                                'name' => 'Bound by Memory',
                                'contents' => "Yet Melusine did not leave entirely. Something of her remained, woven into the stone, the mortar, the hollow places where the wind sang.\n\nOn the night before a lord of Lusignan is to die, she appears on the tallest tower, dressed in white that glows faintly in the dark, her hair loose and streaming in the wind. She stands against the sky, gazing out over the valleys her sons once crossed, and she weeps. Her weeping sounds like water running over stone, like the last note of a song fading into silence.\n\nShe remains **bound** to the castle — bound by love, by memory, by the sons she bore there, by the life she built and lost.",
                            ],
                            [
                                'name' => 'The Penitent',
                                'contents' => "Raymondin left Lusignan that same year, before the new grass had covered the scorched earth of Malliers. He wore a threadbare cloak and walked barefoot, a penitent without peace. He travelled the roads of Poitou, the same roads he had once ridden as a lord — past the oak forest where he had killed his uncle, past the fountain where Melusine had found him.\n\nHe visited every church, every shrine, every hermit's cave in the region. He knelt on cold stone floors and prayed until his knees bled. He gave his rings to beggars, his cloak to the poor. But peace would not come.\n\nSeven years later, in a wooden hut at the edge of a forest, he died. A single candle burned beside him. A priest held his hand. The room smelled of rain and wet earth. His last word was **her name** — spoken not in anger, not in sorrow, but like a man reaching for something he had dropped in the dark.",
                            ],
                            [
                                'name' => 'The Ardennes',
                                'contents' => "Far north, beyond the lands of any lord, the mountains of the Ardennes rose dark and forested, their slopes shrouded in mist. In the caves that pocked the limestone cliffs, **Horrible** grew large and wild. The cave he claimed as his own was deep, its walls damp with mineral seepage, its floor littered with the bones of deer and wild boar. He fed on raw meat and drank from cold mountain streams. His scales thickened. His claws grew long.\n\nOn certain nights, when the moon was full and the wind blew from the south, he would emerge from his cave and stand on the ledge, his yellow eyes fixed on the distant horizon where Lusignan lay. He would hiss — a low, mournful sound that carried no curse, only longing.\n\nHe never saw his mother again.",
                            ],
                            [
                                'name' => 'One by One',
                                'contents' => "Across Europe, the deaths came **one by one**, like candles being extinguished in a long hall.\n\n**Guyon** fell in Luxembourg, defending the duke who had honoured him. A crossbow bolt through the throat on a grey morning. He died in the mud, his cat's eye still open, staring at the sky.\n\n**Antoine** died in Armenia, his adopted kingdom burning around him. The Sultan had returned with an army three times the size of the last, and Antoine stood on the walls until they collapsed beneath him.\n\n**Reynaud** was killed in a French campaign against the English. An arrow in the eye — the higher one — at twilight, in a field of wet wheat.\n\n**Geoffroy** was never seen after his father's death. Some say he sailed to the Holy Land and died in the desert. Some say he followed his mother into the otherworld through a door in the rocks. Some say he still walks the roads of Poitou at night, a giant with a tusk, searching for a fight he cannot win.\n\n**Thierry** lived longest. He held Vouvant for forty years and died in his bed, surrounded by his children and grandchildren, the fire crackling in the hearth. His red eye closed peacefully, like a lantern finally spent.\n\n**Raymondet** — the three-eyed, the beloved, the one his mother had loved most — entered a monastery on the coast and spent his life copying books in a script so beautiful that travellers came from other kingdoms to see it. No one knows when he died. His grave is unmarked.",
                            ],
                            [
                                'name' => 'Stories from the Caves',
                                'contents' => "And Horrible? In the caves of the north, around campfires that pop and hiss in the darkness, the hunters tell stories of a beast with scales and yellow eyes. They say it has a man's shape but a serpent's hunger, and that it never attacks unless provoked. They say it moves through the forest without sound, leaving footprints that are half-human, half-clawed beast.\n\nThey do not know it is the **last son of Melusine**.",
                            ],
                            [
                                'name' => 'A Heap of Broken Stone',
                                'contents' => "Over the centuries, the castle passed from hand to hand — from lords to heirs to conquerors. The line of Lusignan faded into other houses: the House of Cyprus, the Kingdom of Jerusalem, the courts of France and England. Melusine's blood ran in kings, but **no king remembered her name**. The tapestries rotted. The iron gates rusted. The rose window of the chapel was shattered by a storm and never repaired.\n\nThe castle crumbled. The towers fell, one by one, until they were nothing but stumps of stone against the sky. The great hall where Melusine had spread her wings, where she had loved and been betrayed, where she had transformed and flown — became a heap of broken stone open to the rain and the stars.",
                            ],
                            [
                                'name' => 'Until the Next Lord Comes Home',
                                'contents' => "To this day, when the wind blows across the ruins of Lusignan — through the broken arches and the empty window-frames — a woman can be heard weeping. The sound comes from the tallest tower, the one that still stands, though its roof is gone and the stars shine through.\n\nAnd on certain nights — when the moon is full and the clouds run low across the valleys — a winged serpent circles the broken towers. **Once. Twice. Three times.** Her wings catch the moonlight, and her shadow passes over the stones like a memory of something lost.\n\nThen she is gone.\n\nUntil the next lord comes home.",
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($acts as $actPosition => $actData) {
            $chapters = $actData['chapters'];
            unset($actData['chapters']);

            $act = $project->acts()->create($actData + ['position' => $actPosition + 1]);

            foreach ($chapters as $chapterPosition => $chapterData) {
                $scenes = $chapterData['scenes'];
                unset($chapterData['scenes']);

                $chapter = $act->chapters()->create($chapterData + ['position' => $chapterPosition + 1]);

                foreach ($scenes as $scenePosition => $sceneData) {
                    $eventTitle = $sceneEvents[$sceneData['name']] ?? null;

                    $chapter->scenes()->create($sceneData + [
                        'position' => $scenePosition + 1,
                        'event_id' => $eventTitle ? $eventsByTitle[$eventTitle]->id : null,
                    ]);
                }
            }
        }

        // See BackfillsSceneWordCounts: model events (and so Scene::booted()'s
        // word_count hook) are off for the whole seeded run.
        $this->backfillSceneWordCounts($project);

        $this->seedCodex($project, $eventsByTitle);
    }

    /**
     * Seed the Codex: attribute definitions, entries (characters/location/organization)
     * with aliases and tags, and the temporal attribute values that tell the hair-color
     * story end to end.
     *
     * Everything here is idempotent (firstOrCreate / upsert), and the temporal values are
     * created by calling the AttributeTimeline service *directly* rather than through any
     * model hook — DatabaseSeeder runs WithoutModelEvents, so hooks (position assignment,
     * baseline creation) never fire. That is also why position is set explicitly on the
     * attribute definitions below.
     *
     * @param  array<string, Event>  $eventsByTitle  named events keyed by title
     */
    private function seedCodex(Project $project, array $eventsByTitle): void
    {
        // Attribute definitions. `applies_to` picks which entry types show each attribute:
        // "Architecture style" is the Location-only one and "Reputation" is deliberately
        // shared by characters and organizations, so the applies-to filtering is exercised
        // from both ends. The character attributes from "Skin color" down are the default
        // character-sheet fields — defined but left unvalued, as a real project would start.
        // `position` is set by hand (the creating hook is off).
        $hairColor = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Hair color'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 1],
        );

        $architectureStyle = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Architecture style'],
            ['applies_to' => [CodexEntryType::Location], 'position' => 2],
        );

        $skinColor = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Skin color'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 3],
        );

        $eyeColor = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Eye color'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 4],
        );

        $build = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Build'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 5],
        );

        $height = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Height'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 6],
        );

        $physicalBuild = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Physical build'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 7],
        );

        $gender = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Gender'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 8],
        );

        $religion = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Religion'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 9],
        );

        $race = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Race'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 10],
        );

        $occupation = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Occupation'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 11],
        );

        $priorities = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Priorities'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 12],
        );

        $secrets = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Secrets'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 13],
        );

        $hobbies = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Hobbies'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 14],
        );

        $fears = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Fears'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 15],
        );

        $fortunes = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Fortunes'],
            ['applies_to' => [CodexEntryType::Organization], 'position' => 16],
        );

        $reputation = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Reputation'],
            ['applies_to' => [CodexEntryType::Character, CodexEntryType::Organization], 'position' => 17],
        );

        // --- Characters ---

        $melusine = $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Mélusine',
            '<p>A faerie of the greenwood, <strong>cursed</strong> to take a serpent form below the waist every Saturday. Wife of Raymondin and mother of the nine sons of Lusignan.</p>',
            // 'Melusine' (unaccented) is the spelling every scene actually uses in prose —
            // the accented 'Mélusine' name alone never whole-word-matches that text.
            ['Melusina', 'Melusine', 'The Serpent Lady', 'Lady of Lusignan'],
            ['Faerie', 'Protagonist', 'Cursed'],
        );

        // Mélusine's hair over time: raven black by default, curse-touched after Pressine's
        // judgment, and loose and wild once she takes her winged serpent form.
        $this->seedPeriods($melusine, $hairColor, [
            [null, 'Raven black, falling to her waist'],
            ['The Second Curse', 'Raven black, run through with silver on Saturdays'],
            ['The Transformation', 'Wild and loose about her wings'],
        ], $eventsByTitle);

        $this->seedPeriods($melusine, $reputation, [
            [null, 'An unknown faerie of the forest fountain'],
            ['The Building of Lusignan', 'The beloved and generous Lady of Lusignan'],
            ['The Transformation', 'Denounced before the court as a serpent-demon'],
        ], $eventsByTitle);

        $raymondin = $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Raymondin of Lusignan',
            '<p>A young knight of Poitou who accidentally kills his uncle, weds Mélusine, and becomes the first <em>Lord of Lusignan</em> — until his broken oath undoes them both.</p>',
            // 'Raymond' is a substring of 'Raymondin', not a separate word, so it never
            // whole-word-matches the scenes — they use 'Raymondin' throughout.
            ['Raymond', 'Raymondin', 'Lord of Lusignan'],
            ['Knight', 'Protagonist'],
        );

        $this->seedPeriods($raymondin, $hairColor, [
            [null, 'Chestnut brown'],
            ['The Transformation', 'Gone grey with grief'],
        ], $eventsByTitle);

        $this->seedPeriods($raymondin, $reputation, [
            [null, 'A minor nephew of the Count of Poitiers'],
            ['The Building of Lusignan', 'The rising Lord of Lusignan'],
            ['The Transformation', 'A broken, penitent widower'],
        ], $eventsByTitle);

        // --- Characters absent from the original seed ---

        $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Pressine',
            '<p>A faerie of the greenwood who marries Count Elinas of Albany on the condition that he never look upon her in childbirth. When he breaks his oath, she flees to Avalon and later lays a curse upon her daughter Mélusine that shapes the whole tale.</p>',
            ['Lady of the Fountain'],
            ['Faerie', 'Mother'],
        );

        $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Count Elinas',
            '<p>A mortal nobleman who meets and marries Pressine at a forest fountain, but breaks his sacred oath by peering through the keyhole while she bathes their newborn daughters. His own daughters later seal him alive within the Branded Mountain.</p>',
            ['Count of Albany', 'Elinas of Albany'],
            ['Noble', 'Father'],
        );

        $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Melior',
            '<p>The second daughter of Pressine and Elinas, sister to Mélusine and Palatine, who joins them in imprisoning their father. Pressine\'s judgment locks her in a tower until a knight brave enough to serve her appears.</p>',
            ['Sister of Mélusine'],
            ['Faerie', 'Sister'],
        );

        $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Palatine',
            '<p>The third daughter of Pressine and Elinas, sister to Mélusine and Melior, who aids in their father\'s imprisonment. She is sentenced to guard the treasure of the mountain until a worthy hero claims her.</p>',
            ['Sister of Mélusine'],
            ['Faerie', 'Sister'],
        );

        $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Count of Poitiers',
            '<p>Raymondin\'s uncle, killed by a stray spear during a boar hunt when the beast swerves at the wrong moment. This accident sets the entire story of Mélusine and Raymondin in motion.</p>',
            ['Uncle of Raymondin'],
            ['Noble'],
        );

        $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Geoffroy of the Big Tooth',
            '<p>The fifth son of Mélusine and Raymondin, marked by a tusk of bone jutting from his jaw and a temper to match. He burns the abbey of Malliers, killing his own brother Fromont, and is never seen again after his father\'s death.</p>',
            ['Geoffroy la Grand\'Dent'],
            ['Son of Lusignan'],
        );

        $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Horrible',
            '<p>The secret ninth son, born with a scaled body, claws, and needle teeth, hidden in the deepest cellar of Lusignan. He breaks free during the fire at Malliers and is sent north to the caves of the Ardennes, where he grows into a wild beast of legend.</p>',
            ['The Cellar Child'],
            ['Son of Lusignan', 'Monster'],
        );

        $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Count of Forez',
            '<p>A jealous nobleman who envies Raymondin\'s rise and deliberately sows doubt in his mind by questioning what kind of woman births marked sons and keeps secret Saturdays, driving Raymondin to spy on Mélusine.</p>',
            ['Forez'],
            ['Noble', 'Antagonist'],
        );

        // --- Location ---

        $castle = $this->seedEntry(
            $project,
            CodexEntryType::Location,
            'Castle of Lusignan',
            '<p>The great white-marble castle Mélusine raised in a <strong>single night</strong> on a thorned promontory above the river.</p>',
            ['Lusignan'],
            ['Castle', 'Poitou'],
        );

        // The castle's fabric across the tale: the bare rock Mélusine found, the marble she
        // raised in a night, and the ruin it falls to. The one Location-scoped attribute
        // with values, so the Codex demo exercises a location timeline as well as a
        // character one.
        $this->seedPeriods($castle, $architectureStyle, [
            [null, 'A bare, thorned promontory of rock'],
            ['The Building of Lusignan', 'Raw white marble, newly raised and unadorned'],
            ['The Fall of Lusignan', 'Roofless stumps of stone, open to the rain'],
        ], $eventsByTitle);

        // --- Locations absent from the original seed ---
        //
        // Several of these carry a lower-case common-noun alias ('fountain', 'caves',
        // 'cellar'). The prose names them that way and nothing else — matching is
        // case-SENSITIVE and whole-word, so without the alias the entry links to no scene
        // at all. Each was checked to mean only this place across the whole seed: 'cave'
        // (singular) is deliberately absent because "every hermit's cave" in The Penitent
        // is a different cave, and 'tower' alone is absent for the same reason.

        $fountain = $this->seedEntry(
            $project,
            CodexEntryType::Location,
            'Fountain of the Forest',
            '<p>A clearing in the forest of Poitou where three streams converge to feed a fountain of white marble, veined with green. Here both Pressine and Mélusine meet their mortal husbands — and here each story begins.</p>',
            ['The Fountain of Thirst', 'The Forest Fountain', 'Lady of the Fountain', 'fountain'],
            ['Fountain', 'Forest'],
        );

        $abbey = $this->seedEntry(
            $project,
            CodexEntryType::Location,
            'Abbey of Malliers',
            '<p>A stone abbey in a valley of oak and ash, burned to the ground by Geoffroy of the Big Tooth in a fit of rage. The fire consumed every monk, including Geoffroy\'s own brother Fromont.</p>',
            ['Malliers'],
            ['Abbey', 'Ruins'],
        );

        $ardenne = $this->seedEntry(
            $project,
            CodexEntryType::Location,
            'Caves of the Ardennes',
            '<p>Dark limestone caves in the northern mountains where Horrible lived after Melusine exiled him there. Hunters tell stories of a beast that emerges on full-moon nights.</p>',
            ['Ardennes Caves', 'The Northern Caves', 'caves'],
            ['Cave', 'Mountains'],
        );

        $branded = $this->seedEntry(
            $project,
            CodexEntryType::Location,
            'Branded Mountain',
            '<p>A peak of black basalt scarred by lightning, where Melusine and her sisters sealed their father Count Elinas alive — bound in faerie silver within a crevice so narrow the rocks press against his ribs.</p>',
            ['Mont de la Brande', 'The Scarred Mountain'],
            ['Mountain', 'Prison'],
        );

        $tower = $this->seedEntry(
            $project,
            CodexEntryType::Location,
            'Tower Chamber of Lusignan',
            '<p>The highest room in the keep of Lusignan, where Melusine spent every Saturday alone — and where Raymondin broke his oath by looking through the keyhole and beheld her serpent form.</p>',
            ["Melusine's Chamber", 'The Saturday Chamber', 'the tower'],
            ['Tower', 'Chamber', 'Oath'],
        );

        $cellar = $this->seedEntry(
            $project,
            CodexEntryType::Location,
            'Cellar of Lusignan',
            '<p>The deepest cellar beneath the castle, a place of damp stone and absolute darkness, where Melusine hid her secret ninth son Horrible — feeding him raw meat through a rusted iron gate.</p>',
            ["Horrible's Cellar", 'The Deep Cellar', 'cellar'],
            ['Cellar', 'Prison'],
        );

        // --- Organization ---

        $house = $this->seedEntry(
            $project,
            CodexEntryType::Organization,
            'House of Lusignan',
            '<p>The noble line founded by Mélusine and Raymondin, whose sons win crowns across Europe and the East before the house fades into other dynasties.</p>',
            ['The Lusignans'],
            ['Noble house'],
        );

        $this->seedPeriods($house, $fortunes, [
            [null, 'Not yet founded'],
            ['The Building of Lusignan', 'Newly established lords of a castle raised by magic'],
            ['The Great Conquests', 'Crowns and titles won across Europe and the East'],
            ['The Fall of Lusignan', 'Faded into other houses; the castle fallen to ruin'],
        ], $eventsByTitle);

        $this->seedPeriods($house, $reputation, [
            [null, 'An unknown name'],
            ['The Great Conquests', 'Renowned throughout Christendom'],
        ], $eventsByTitle);
    }

    /**
     * Create (idempotently) one Codex entry with its aliases and tags.
     *
     * Aliases are firstOrCreate'd children; tags are firstOrCreate'd once per project name
     * and attached without detaching, so entries can share tags (e.g. "Protagonist").
     *
     * @param  array<int, string>  $aliases
     * @param  array<int, string>  $tagNames
     */
    private function seedEntry(
        Project $project,
        CodexEntryType $type,
        string $name,
        string $description,
        array $aliases,
        array $tagNames,
    ): CodexEntry {
        $entry = $project->codexEntries()->firstOrCreate(
            ['type' => $type, 'name' => $name],
            ['description' => $description],
        );

        foreach ($aliases as $alias) {
            $entry->aliases()->firstOrCreate(['alias' => $alias]);
        }

        $tagIds = collect($tagNames)->map(
            fn (string $tagName) => $project->tags()->firstOrCreate(['name' => $tagName])->id,
        );

        $entry->tags()->syncWithoutDetaching($tagIds);

        return $entry;
    }

    /**
     * Seed the temporal periods for one (entry, attribute) pair via AttributeTimeline.
     *
     * Each period is `[$eventTitle, $value]`. A null title is the Start-anchored baseline
     * (invariant #1: every valued pair has exactly one Start value) created with
     * `ensureBaseline`; a title anchors the value at that named event via `upsertAt`. Both
     * service methods run fine WithoutModelEvents and are idempotent on re-seed.
     *
     * @param  array<int, array{0: ?string, 1: string}>  $periods
     * @param  array<string, Event>  $eventsByTitle
     */
    private function seedPeriods(
        CodexEntry $entry,
        CodexAttribute $attribute,
        array $periods,
        array $eventsByTitle,
    ): void {
        $timeline = new AttributeTimeline($entry, $attribute);

        foreach ($periods as [$eventTitle, $value]) {
            if ($eventTitle === null) {
                $timeline->ensureBaseline($value);

                continue;
            }

            $timeline->upsertAt($eventsByTitle[$eventTitle], $value);
        }
    }
}

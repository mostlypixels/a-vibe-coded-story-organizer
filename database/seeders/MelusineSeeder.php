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

        foreach ($events as $eventData) {
            $plotlines = $eventData['plotlines'];
            unset($eventData['plotlines']);

            $event = $project->events()->create($eventData);
            $event->plotlines()->attach(collect($plotlines)->pluck('id'));
        }

        $acts = [
            [
                'name' => 'Act I — The Curse of Pressine',
                'description' => "Pressine's marriage to Count Elinas, the broken oath, and the curse she lays on her daughters.",
                'chapters' => [
                    [
                        'name' => 'The Oath at the Fountain',
                        'description' => 'Count Elinas meets Pressine at a forest fountain and swears never to look upon her in childbirth.',
                        'scenes' => [
                            [
                                'name' => 'A Lady at the Fountain',
                                'contents' => "Long ago in the forest of Brittany, Count Elinas of Albany went hunting and lost his way. Deep in the greenwood he found a fountain where a lady sat, combing her hair with a comb of gold. Her name was Pressine, and she was of the faerie blood.\n\n\"Lady,\" said Elinas, \"I have never seen one so fair. Will you be my wife?\"\n\nPressine looked at him with eyes the color of deep water. \"I will, my lord, on one condition. You must never look upon me when I give birth to our children. Swear it.\"\n\nElinas swore by the bones of his fathers.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Broken Oath',
                        'description' => 'Elinas peers through the keyhole and sees Pressine bathing their infant daughters. Pressine flees to Avalon.',
                        'scenes' => [
                            [
                                'name' => 'Through the Keyhole',
                                'contents' => "Pressine bore him three daughters in a single birth — Melusine, Melior, and Palatine. But Elinas, hearing the cries from the chamber, grew fearful for his wife. He crept to the door and peered through the keyhole.\n\nHe saw Pressine in a silver bath, washing the blood from her infant daughters. Her eyes met his through the crack.\n\n\"Foolish man,\" she wept. \"You have broken your oath. Now I must leave you, and our daughters shall carry the weight of your betrayal.\"\n\nShe gathered the three girls and vanished to the hidden island of Avalon, where no mortal man could follow.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Vengeance',
                        'description' => 'Melusine, Melior, and Palatine seal Count Elinas alive within the Branded Mountain.',
                        'scenes' => [
                            [
                                'name' => 'The Branded Mountain',
                                'contents' => "Years passed. On Avalon, Pressine raised her daughters in the old arts — the songs of the wind, the language of stone, the weaving of fate.\n\nMelusine grew beautiful and proud. When she learned how her father had broken his vow and driven her mother away, her blood burned.\n\n\"We must make him pay,\" she told Melior and Palatine.\n\nThat night the three sisters crossed the sea. They found Count Elinas sleeping in his hall. They bound him in chains of faerie silver and sealed him alive within the Branded Mountain, where the rocks press close and no light reaches. There he remains, breathing stone, dreaming of the wife he lost.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Second Curse',
                        'description' => 'Pressine curses Melusine to become a serpent from the waist down every Saturday.',
                        'scenes' => [
                            [
                                'name' => "Pressine's Judgment",
                                'contents' => "Pressine discovered what her daughters had done. Her face, usually warm, became cold as winter water.\n\n\"You have broken the sacred law,\" she said. \"A child must not raise a hand against a parent. For this, Melusine, you shall bear the heaviest curse.\"\n\nMelusine knelt. \"Mother, I only sought justice for you.\"\n\n\"This is your justice. Every Saturday, from your waist down, you shall become a serpent. If a mortal man marries you and never sees you on a Saturday, you may live as a woman and die as a woman. But if he sees, and if he speaks of it to another — you shall become a winged serpent forever, condemned to wander the earth until the Last Judgment.\"\n\n\"And my sisters?\"\n\n\"Melior shall be locked in a tower until a knight brave enough to serve her appears. Palatine shall guard the treasure of the mountain until a worthy hero claims her.\"\n\nMelusine rose. \"Then I shall find a mortal man who will keep my secret. And I shall bear sons who will shake the world.\"",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Act II — The Meeting at the Fountain of Thirst',
                'description' => 'Raymondin accidentally kills his uncle, flees into the forest, and pledges himself to Melusine.',
                'chapters' => [
                    [
                        'name' => 'The Accidental Blow',
                        'description' => 'Hunting a boar, Raymondin accidentally kills his uncle, the Count of Poitiers, with a stray spear.',
                        'scenes' => [
                            [
                                'name' => 'The Boar Hunt',
                                'contents' => "In the county of Poitou, a young knight named Raymondin rode beside his uncle, the Count of Poitiers, hunting a great boar. The beast crashed through the underbrush, and the Count spurred his horse forward.\n\n\"Now, nephew! Strike!\"\n\nRaymondin hurled his spear. The boar swerved. The spear struck the Count through the ribs, piercing his heart.\n\nThe old man fell from his horse and did not move.\n\nRaymondin knelt beside him, his hands red. \"Uncle — I did not mean — the boar —\"\n\nBut the dead cannot hear.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Lady at the Fountain',
                        'description' => 'Raymondin flees into the forest and meets Melusine, who offers him wealth and marriage in exchange for his secrecy.',
                        'scenes' => [
                            [
                                'name' => 'A Meeting in the Clearing',
                                'contents' => "Raymondin fled into the forest, his mind unhinged. He rode until his horse collapsed, then walked until his boots wore through. At last he came to a clearing where three streams fed a marble fountain.\n\nHe fell to his knees and drank.\n\n\"Your sorrow is deep, knight.\"\n\nHe looked up. A woman stood before him in a gown of silver cloth. Her hair fell to her waist, black as a raven's wing. Her face was the most beautiful he had ever seen.\n\n\"I am Melusine,\" she said. \"I know what you have done. It was the boar's fault, not yours. The beast deceived your aim.\"\n\n\"How can you know this?\"\n\n\"I know many things. I know that if you return to Poitiers, the Count's men will call you murderer. They will hang you from the walls.\"\n\nRaymondin wept. \"Then I am already dead.\"\n\n\"You are not. I can give you lands, a castle, wealth beyond counting. I can make you the greatest lord in Poitou. If you will take me as your wife.\"\n\n\"You would marry a man with blood on his hands?\"\n\n\"I would marry a man with a true heart who made a terrible mistake.\"",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Oath of Saturday',
                        'description' => 'Raymondin swears never to look upon Melusine on a Saturday, and the two are betrothed.',
                        'scenes' => [
                            [
                                'name' => 'The Vow',
                                'contents' => "Raymondin looked at her and saw no mockery, no deceit. \"I will marry you,\" he said.\n\nMelusine smiled. \"But you must swear one thing. Every Saturday, I must be alone. I must lock myself in my chamber, and no one — not even you — may look upon me. Swear this, and I will give you everything.\"\n\n\"I swear it on my soul.\"\n\n\"Then come,\" she said, taking his hand. \"Let me show you where we shall live.\"",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Act III — The Castle and the Sons',
                'description' => 'Melusine raises the Castle of Lusignan overnight, weds Raymondin, and bears eight sons — and a hidden ninth.',
                'chapters' => [
                    [
                        'name' => 'The Building of Lusignan',
                        'description' => 'In a single night, Melusine raises the Castle of Lusignan from a thorned promontory.',
                        'scenes' => [
                            [
                                'name' => 'A Castle Raised in a Night',
                                'contents' => "Melusine led Raymondin to a wild promontory overlooking the river — a jagged rock covered in thorns and brambles.\n\n\"Here,\" she said, \"I will build you a castle.\"\n\nThat night, Raymondin slept under an oak. Melusine walked to the rock and raised her arms. She summoned powers older than the cross — the spirits of the earth, the whisperers in the stone.\n\nAll night the ground trembled. Blocks of white marble rose from the soil. Towers pushed upward like growing trees. Walls wove themselves together. By dawn, the Castle of Lusignan stood complete — gates, battlements, great hall, and chapel.\n\nRaymondin woke and saw it. He fell to his knees.\n\n\"It is a miracle,\" he whispered.\n\n\"It is a home,\" said Melusine.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Wedding',
                        'description' => "Melusine and Raymondin marry, and she withdraws to her chamber every Saturday without explanation.",
                        'scenes' => [
                            [
                                'name' => 'Lord and Lady of Lusignan',
                                'contents' => "The Bishop of Poitiers married them in the castle chapel. Melusine's beauty was spoken of in every hall in France. Her generosity was bottomless — she gave rich gifts to every guest, and none left hungry.\n\nRaymondin was proclaimed Lord of Lusignan.\n\nBut every Saturday, Melusine withdrew to her tower chamber. She barred the door with iron and spoke to no one. Servants whispered. The kitchen maids crossed themselves. Raymondin said nothing.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Birth of the Eight Sons',
                        'description' => 'Melusine bears Raymondin eight sons, each marked by her faerie blood, and secretly hides a ninth, Horrible, in the cellar.',
                        'scenes' => [
                            [
                                'name' => 'Eight Marked Sons',
                                'contents' => "In time, Melusine bore Raymondin eight sons. Each came into the world with a strange mark, a sign of his mother's faerie blood.\n\nUrien, the firstborn, had a single red ear — the ear of a wolf. He would become a king in Cyprus.\n\nGuyon, the second, had a left eye that gleamed like a cat's in darkness. He would slay a giant in Luxembourg.\n\nAntoine, the third, bore a claw-mark on his cheek where his mother's nail had grazed him in the night. He would conquer half of Armenia.\n\nReynaud, the fourth, had one eye set higher than the other. He would become Constable of France.\n\nGeoffroy, the fifth — called Geoffroy of the Big Tooth — had a tusk of bone jutting from his jaw. His temper was a furnace. He would burn an abbey and kill his own brother.\n\nFromont, the sixth, bore a red mark across his nose like a monk's hood. He alone was gentle. He entered the Church and prayed for his family's sins.\n\nThierry, the seventh, had one eye red as blood. He would become Lord of Vouvant.\n\nRaymondet, the eighth and last, had three eyes — two in the usual place and one above his nose. His mother loved him most of all.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'Horrible, the Hidden Ninth',
                        'description' => 'A ninth son, scaled and monstrous, is born in secret and hidden in the deepest cellar of Lusignan.',
                        'scenes' => [
                            [
                                'name' => 'The Cellar Child',
                                'contents' => "But there was a ninth son, born in secret, whom Melusine hid in the deepest cellar of Lusignan. His name was Horrible.\n\nHe had no mark on his face — his entire body was the mark. His skin was scaled like a lizard's. His fingers ended in claws. His teeth were needles. He grew in the dark, feeding on raw meat, speaking in hisses.\n\nMelusine visited him in the night. \"You are my son,\" she whispered, \"but you cannot walk among men. You would terrify them.\"\n\nHorrible gnawed a bone and said nothing.",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Act IV — The Great Conquests',
                'description' => 'The sons of Melusine go to war, each in a different land, at the same time.',
                'chapters' => [
                    [
                        'name' => 'The Fleet and the Giant and the Siege and the Court',
                        'description' => 'Urien, Guyon, Antoine, and Reynaud each depart in the same month to win crowns and titles across Europe and the East.',
                        'scenes' => [
                            [
                                'name' => 'Four Ships Leave Poitou',
                                'contents' => "In the same month, four ships left the harbor of Poitou.\n\nUrien sailed east to the island of Cyprus. The Saracens held the coast, but Urien burned their fleet and broke their lines in a single morning. The King of Cyprus gave him his daughter, and Urien wore a crown.\n\nGuyon rode north to Luxembourg. A giant named Maldichet had been eating children from the villages. Guyon met him on a stone bridge over the river Sure. They fought for three hours. The giant crushed Guyon's shield. Guyon cut the giant's hamstrings, then drove his sword up under the ribs. The Duke of Luxembourg knighted him on the battlefield.\n\nAntoine sailed east to Armenia. The Sultan had besieged the capital for seven years. Antoine rode out alone before the walls and challenged the Sultan's champion. One stroke — from saddle to chin. The Armenian King offered half his kingdom and his daughter's hand.\n\nReynaud rode to Paris, to the court of the King of France. He joined the royal army, fought in three campaigns, and never lost a skirmish. The King made him Constable of France, the highest knight in the realm.",
                            ],
                            [
                                'name' => 'Melusine at the Spinning Wheel',
                                'contents' => "While all this happened, Melusine sat in her tower at Lusignan, spinning thread of silver. She could see each of her sons in the thread — Urien on his throne, Guyon at the giant's corpse, Antoine wiping his sword, Reynaud kneeling before the King.\n\n\"Four are safe,\" she said. \"But the fifth —\"\n\nShe saw fire.",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Act V — The Damned Deed',
                'description' => 'Geoffroy burns the abbey of Malliers, killing his own brother, and Horrible escapes into the castle.',
                'chapters' => [
                    [
                        'name' => 'The Burning of Malliers',
                        'description' => 'Geoffroy burns the abbey of Malliers in a fit of rage, killing his gentle brother Fromont, who was inside at prayer.',
                        'scenes' => [
                            [
                                'name' => "Geoffroy's Fire",
                                'contents' => "Geoffroy had stayed in Poitou. His temper grew with his strength. When the Abbot of Malliers refused him passage through the abbey's hunting grounds, Geoffroy's blood caught flame.\n\n\"Deny me?\" he roared. \"I am a son of Lusignan. No monk will bar my way.\"\n\nThat night he returned with torches. He set fire to the abbey roof. The flames spread from the chapel to the dormitory to the library. Monks ran screaming into the night, their robes on fire.\n\nInside the abbey, visiting for prayers, was Fromont — Geoffroy's own brother, the gentle sixth son.\n\nFromont died in the flames, his monk's cowl burning on his face.",
                            ],
                        ],
                    ],
                    [
                        'name' => "Horrible's Hunger",
                        'description' => 'Horrible breaks free from his cellar cage and kills servants in the castle kitchens before Melusine sends him away.',
                        'scenes' => [
                            [
                                'name' => 'Breaking the Cage',
                                'contents' => "In the cellar of Lusignan, Horrible felt the fire. He smelled it through the stone. He heard the screaming in his blood.\n\nHe broke the iron bars of his cage and crawled up into the castle.\n\nThat night, three servants vanished. In the morning, only bones were found.\n\nMelusine found Horrible in the kitchens, crouched over a fourth body. She did not scold him.\n\n\"You cannot stay here,\" she said softly. \"You will destroy everything.\"\n\nShe led him to a secret tunnel beneath the castle, a passage that opened into the deep woods. \"Go north,\" she said. \"Find the mountains. Live in the caves. I will send you food.\"\n\nHorrible looked at her with yellow eyes. \"Mother,\" he hissed. That was the only word he ever spoke.\n\nHe vanished into the dark.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Poison Arrives',
                        'description' => "News of Malliers reaches Lusignan, and the Count of Forez plants doubt about Melusine's secret Saturdays.",
                        'scenes' => [
                            [
                                'name' => 'Two Messengers',
                                'contents' => "Two messengers reached Lusignan on the same day.\n\nThe first brought word of Malliers. \"Geoffroy has burned the abbey of Malliers. Every monk is dead. And Fromont — your son Fromont — was among them.\"\n\nRaymondin's face went gray. \"My son killed his brother?\"\n\n\"The fire took him, my lord. Whether by Geoffroy's hand or the flames, no one knows.\"\n\nThe second messenger was the Count of Forez, a jealous nobleman who had long resented Raymondin's rise.\n\n\"Tragic news, my lord,\" said Forez, pouring wine. \"But I wonder — have you never questioned your wife's strange ways? The secret Saturdays? The sudden wealth? The sons born with claws and tusks?\"\n\n\"Hold your tongue,\" Raymondin said.\n\n\"I am only thinking of you, my friend. What kind of woman births monsters?\"",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Act VI — The Broken Oath',
                'description' => "Raymondin looks through the keyhole, sees Melusine's serpent form, and word arrives of Urien's death.",
                'chapters' => [
                    [
                        'name' => 'The Keyhole',
                        'description' => "Consumed by doubt, Raymondin looks through the keyhole of Melusine's chamber and sees her serpent form.",
                        'scenes' => [
                            [
                                'name' => 'What Raymondin Saw',
                                'contents' => "That Saturday, Raymondin could not rest. He walked the corridors of his castle like a ghost. The words of Forez echoed in his skull.\n\n*What kind of woman births monsters?*\n\nHe found himself outside Melusine's chamber. A sliver of candlelight bled through the keyhole.\n\nHe knelt.\n\nHe looked.\n\nInside, Melusine sat in a great marble bath filled with steaming water. From the waist up, she was the woman he had married — her white shoulders, her dark hair, her face calm as carved ivory. But from the waist down, her body was a serpent — a long, thick tail covered in scales of silver and blue, coiling and uncoiling in the water like a living thing.\n\nRaymondin's heart stopped. He fell backward, his hand over his mouth.\n\nHe crept away without a sound.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Silence of Seven Days',
                        'description' => 'Raymondin cannot meet his wife\'s eyes for a week; Melusine senses that he knows.',
                        'scenes' => [
                            [
                                'name' => 'A Week of Silence',
                                'contents' => "For a week, Raymondin said nothing. He sat at dinner across from Melusine and could not meet her eyes. He touched her hand and felt a tremor of fear.\n\nMelusine watched him. She knew.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The News and the Breaking',
                        'description' => "Word of Urien's death arrives, and Raymondin denounces Melusine as a serpent before the whole court.",
                        'scenes' => [
                            [
                                'name' => "Urien's Death",
                                'contents' => "The third messenger arrived seven days later. Urien was dead in Cyprus. The Saracens had ambushed him in a mountain pass. He had died sword in hand, covered in wounds, but he had not fallen until his men were safe.\n\nRaymondin received the news in the great hall, surrounded by his knights.",
                            ],
                            [
                                'name' => 'The Denouncement',
                                'contents' => "He stood up. His face was pale as bone.\n\n\"I have kept a secret too long,\" he said. \"I have protected a demon in my own house.\"\n\n\"Raymondin —\" said Melusine, entering the hall. \"Do not speak.\"\n\n\"My wife is a serpent!\" Raymondin shouted, pointing at her. \"Every Saturday, she becomes a snake below the waist. I saw her with my own eyes. Our children — these monsters — are the spawn of a faerie beast!\"\n\nThe hall erupted. Knights crossed themselves. Ladies screamed.",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Act VII — The Transformation',
                'description' => 'Melusine transforms into a winged serpent before the court and flies from Lusignan forever.',
                'chapters' => [
                    [
                        'name' => 'The Silver Wings',
                        'description' => 'After Raymondin denounces her before the whole court, Melusine transforms into a winged serpent and flies from Lusignan forever.',
                        'scenes' => [
                            [
                                'name' => 'A Flower Opening',
                                'contents' => "Melusine stood in the center of the hall. She did not weep. She did not rage. Her face held only an ancient, bottomless sorrow.\n\n\"You broke your oath,\" she said. \"You looked. And now you have spoken.\"\n\n\"Your son killed his brother!\" Raymondin cried. \"Your other son claws men apart in the dark! You are cursed, woman, and you have cursed me!\"\n\n\"I never cursed you. I loved you. I gave you everything.\"\n\nShe spread her arms. Her form began to change — not painfully, but like a flower opening. Her gown dissolved into mist. Wings of silver and white unfolded from her shoulders, wide enough to touch the walls. Her legs fused, lengthened, became a serpent's tail. Scales of pearl and sapphire climbed her waist, her ribs, her throat.\n\nShe rose into the air — a dragon-woman, beautiful and terrible.\n\n\"I loved you,\" she said. \"I loved our sons. I have loved no one else in all the long years of my life.\"\n\nShe circled the great hall once, then flew through the window into the night.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Three Circuits',
                        'description' => 'Melusine flies three times around the Castle of Lusignan before vanishing into the clouds.',
                        'scenes' => [
                            [
                                'name' => 'Around the Towers',
                                'contents' => "Melusine flew three times around the Castle of Lusignan. Her cry was not a scream — it was a sound like a breaking harp, like ice cracking on a frozen river. It was heard in every village within a day's ride.\n\nOn the first circuit, the towers wept mortar.\n\nOn the second circuit, the gates groaned and split.\n\nOn the third circuit, she rose into the clouds and was gone.",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Act VIII — The Haunting',
                'description' => 'Melusine remains bound to Lusignan in spirit; Raymondin dies a penitent, and Horrible grows wild in the mountains.',
                'chapters' => [
                    [
                        'name' => 'The Weeping Woman',
                        'description' => 'Melusine appears atop the tallest tower on the night before a lord of Lusignan is to die.',
                        'scenes' => [
                            [
                                'name' => 'Bound by Memory',
                                'contents' => "Melusine did not leave entirely.\n\nOn the night before a lord of Lusignan is to die, she appears on the tallest tower, dressed in white, her hair loose to the wind. She weeps, and her weeping sounds like water running over stone.\n\nShe is still bound to the castle — bound by love, by memory, by the sons she bore there.",
                            ],
                        ],
                    ],
                    [
                        'name' => "Raymondin's End",
                        'description' => 'Raymondin wanders Poitou as a penitent and dies alone seven years later, speaking only her name.',
                        'scenes' => [
                            [
                                'name' => 'The Penitent',
                                'contents' => "Raymondin left Lusignan that same year. He wandered the roads of Poitou in a threadbare cloak, a penitent without peace. He visited every church, every shrine, every hermit's cave.\n\nHe died seven years later in a wooden hut, alone except for a priest.\n\nHis last word was her name.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'Horrible in the Mountains',
                        'description' => 'Far north in the Ardennes, Horrible grows wild and never sees his mother again.',
                        'scenes' => [
                            [
                                'name' => 'The Ardennes',
                                'contents' => "Far north, in the caves of the Ardennes, Horrible grew large and wild. He ate deer raw and drank from mountain streams. Sometimes, at night, he would look south toward Lusignan and hiss.\n\nHe never saw his mother again.",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Act IX — The End of the Blood',
                'description' => 'The sons of Melusine fall one by one, and the line and castle of Lusignan pass into ruin and legend.',
                'chapters' => [
                    [
                        'name' => 'The Sons Fall',
                        'description' => 'Guyon, Antoine, Reynaud, and Geoffroy meet their fates, while Thierry and Raymondet live on.',
                        'scenes' => [
                            [
                                'name' => 'One by One',
                                'contents' => "The deaths came one by one.\n\nGuyon fell in Luxembourg, defending the duke who had honored him. A crossbow bolt through the throat.\n\nAntoine died in Armenia, his kingdom burning around him when the Sultan returned with a larger army.\n\nReynaud was killed in a French campaign against the English. An arrow in the eye — the higher one.\n\nGeoffroy was never seen after his father's death. Some say he sailed to the Holy Land. Some say he followed his mother into the otherworld. Some say he still walks the roads of Poitou, a giant with a tusk, searching for a fight he cannot win.\n\nThierry lived longest. He held Vouvant for forty years and died in his bed, surrounded by his children. His red eye closed peacefully.\n\nRaymondet — the three-eyed, the beloved — entered a monastery and spent his life copying books. No one knows when he died.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Last Son',
                        'description' => 'Hunters in the north tell stories of a scaled beast, never knowing it is the last son of Melusine.',
                        'scenes' => [
                            [
                                'name' => 'Stories from the Caves',
                                'contents' => "And Horrible? In the caves of the north, hunters tell stories of a beast with scales and yellow eyes. They say it has a man's shape but a serpent's hunger. They say it never attacks unless provoked.\n\nThey do not know it is the last son of Melusine.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Fall of Lusignan',
                        'description' => 'Generations later, the line of Lusignan fades and the castle crumbles to ruin, though Melusine is still said to circle its towers.',
                        'scenes' => [
                            [
                                'name' => 'A Heap of Broken Stone',
                                'contents' => "The castle passed from hand to hand. The line of Lusignan faded into other houses — the House of Cyprus, the Kingdom of Jerusalem, the courts of France and England. Melusine's blood ran in kings, but no king remembered her name.\n\nThe castle crumbled. The towers fell. The great hall where Melusine spread her wings became a heap of broken stone.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'The Last Cry',
                        'description' => 'On certain nights, a winged serpent still circles the ruins of Lusignan three times before vanishing.',
                        'scenes' => [
                            [
                                'name' => 'Until the Next Lord Comes Home',
                                'contents' => "To this day, when the wind blows across the ruins of Lusignan, a woman can be heard weeping.\n\nAnd on certain nights — when the moon is full and the clouds run low — a winged serpent circles the broken towers. Once. Twice. Three times.\n\nThen she is gone.\n\nUntil the next lord comes home.",
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($acts as $actData) {
            $chapters = $actData['chapters'];
            unset($actData['chapters']);

            $act = $project->acts()->create($actData);

            foreach ($chapters as $chapterData) {
                $scenes = $chapterData['scenes'];
                unset($chapterData['scenes']);

                $chapter = $act->chapters()->create($chapterData);

                foreach ($scenes as $sceneData) {
                    $chapter->scenes()->create($sceneData);
                }
            }
        }
    }
}

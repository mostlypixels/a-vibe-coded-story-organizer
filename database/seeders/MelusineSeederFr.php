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
use Illuminate\Database\Seeder;

class MelusineSeederFr extends Seeder
{
    /**
     * Seed a sample "Roman de Melusine" project (French) with plotlines and events.
     */
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        // Rich-HTML description: showcases the new format (a heading + a list) so the
        // Story overview and detail pages render real markup. Every string is within the
        // sanitizer allow-list; the set-mutator on Project::description cleans it on write
        // regardless (see App\Models\Concerns\SanitizesRichHtml).
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Le Roman de Mélusine',
            'language' => BookLanguage::French,
            'description' => <<<'HTML'
                <p>Une légende médiévale de la fée <strong>Mélusine</strong>, sa malédiction, son mariage avec <em>Raymondin de Lusignan</em>, et le destin de leurs neuf fils.</p>
                <h3>Les fils du récit</h3>
                <ul>
                    <li>La malédiction que Pressine jette sur ses filles.</li>
                    <li>Le pacte et le mariage de Mélusine et Raymondin.</li>
                    <li>Les conquêtes et les tragédies des fils de Lusignan.</li>
                </ul>
                HTML,
        ]);

        $mainPlotline = $project->plotlines()->firstWhere('is_main', true)
            ?? Plotline::create([
                'project_id' => $project->id,
                'name' => 'Intrigue principale',
                'is_main' => true,
                'color' => PlotlineColors::PRESETS[0], // red-500
            ]);

        $curseOfPressine = Plotline::create([
            'project_id' => $project->id,
            'name' => 'La malédiction de Pressine',
            'description' => "<p>Le mariage de Pressine avec le comte Elinas, le <strong>serment brisé</strong>, et la malédiction qu'elle jette sur ses filles.</p>",
            'color' => PlotlineColors::PRESETS[8], // green-500
        ]);

        $melusineAndRaymondin = Plotline::create([
            'project_id' => $project->id,
            'name' => 'Mélusine et Raymondin',
            'description' => "<p>La cour, le mariage, et la <em>perte</em> finale de Mélusine et Raymondin de Lusignan.</p>",
            'color' => PlotlineColors::PRESETS[16], // sky-500
        ]);

        $sonsOfLusignan = Plotline::create([
            'project_id' => $project->id,
            'name' => 'Les fils de Lusignan',
            'description' => "<p>Les conquêtes, les triomphes et les tragédies des <strong>neuf fils</strong> de Mélusine et Raymondin.</p>",
            'color' => PlotlineColors::PRESETS[24], // purple-500
        ]);

        foreach ([
            ['title' => 'Début', 'event_datetime' => '0001-01-01 00:00:00'],
            ['title' => 'Fin', 'event_datetime' => '3000-01-01 00:00:00'],
        ] as $bookend) {
            $bookendEvent = $project->events()->firstOrCreate(
                ['title' => $bookend['title']],
                $bookend + ['is_fixed' => true],
            );
            $bookendEvent->plotlines()->syncWithoutDetaching($mainPlotline->id);
        }

        $events = [
            [
                'title' => 'Le serment à la fontaine',
                'description' => 'Le comte Elinas rencontre Pressine à une fontaine de la forêt et jure de ne jamais la regarder en couches.',
                'event_datetime' => '1000-03-01 12:00:00',
                'plotlines' => [$curseOfPressine, $mainPlotline],
            ],
            [
                'title' => 'Le serment brisé',
                'description' => "Elinas regarde par le trou de la serrure et voit Pressine baignant leurs filles nouveau-nées. Pressine s'enfuit à Avalon.",
                'event_datetime' => '1001-01-10 09:00:00',
                'plotlines' => [$curseOfPressine],
            ],
            [
                'title' => 'La vengeance',
                'description' => 'Mélusine, Mélior et Palatine scellent le comte Elinas vivant dans la Montagne Marquée.',
                'event_datetime' => '1016-06-15 22:00:00',
                'plotlines' => [$curseOfPressine],
            ],
            [
                'title' => 'La seconde malédiction',
                'description' => 'Pressine maudit Mélusine à devenir un serpent de la taille aux pieds chaque samedi.',
                'event_datetime' => '1016-06-20 08:00:00',
                'plotlines' => [$curseOfPressine, $melusineAndRaymondin],
            ],
            [
                'title' => 'Le coup accidentel',
                'description' => "En chassant un sanglier, Raymondin tue accidentellement son oncle, le comte de Poitiers, d'un coup de lance égaré.",
                'event_datetime' => '1035-09-02 07:30:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'La rencontre à la fontaine de Soif',
                'description' => 'Raymondin fuit dans la forêt et rencontre Mélusine, qui lui offre richesse et mariage en échange de son silence.',
                'event_datetime' => '1035-09-03 20:00:00',
                'plotlines' => [$melusineAndRaymondin],
            ],
            [
                'title' => 'La construction de Lusignan',
                'description' => 'En une seule nuit, Mélusine élève le château de Lusignan sur un promontoire épineux.',
                'event_datetime' => '1035-10-01 05:00:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'La naissance des huit fils',
                'description' => 'Mélusine donne à Raymondin huit fils, chacun marqué par son sang féerique, et cache secrètement un neuvième, Horrible, dans les caves.',
                'event_datetime' => '1042-04-12 03:00:00',
                'plotlines' => [$melusineAndRaymondin, $sonsOfLusignan],
            ],
            [
                'title' => 'Les grandes conquêtes',
                'description' => "Urien, Guyon, Antoine et Reynaud partent tous le même mois pour conquérir couronnes et titres à travers l'Europe et l'Orient.",
                'event_datetime' => '1060-05-01 06:00:00',
                'plotlines' => [$sonsOfLusignan],
            ],
            [
                'title' => "L'incendie de Malliers",
                'description' => "Geoffroy, pris de rage, incendie l'abbaye de Malliers, tuant son doux frère Fromont, qui s'y trouvait en prière.",
                'event_datetime' => '1061-11-30 23:00:00',
                'plotlines' => [$sonsOfLusignan],
            ],
            [
                'title' => 'Le trou de la serrure',
                'description' => 'Rongé par le doute, Raymondin regarde par le trou de la serrure de la chambre de Mélusine et voit sa forme de serpent.',
                'event_datetime' => '1061-12-07 21:00:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'La transformation',
                'description' => "Après que Raymondin l'a dénoncée devant toute la cour, Mélusine se transforme en serpent ailé et s'envole de Lusignan pour toujours.",
                'event_datetime' => '1061-12-14 19:00:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'La chute de Lusignan',
                'description' => "Des générations plus tard, la lignée de Lusignan s'éteint et le château tombe en ruine, bien que l'on dise que Mélusine survole encore ses tours.",
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
            'Une dame à la fontaine' => 'Le serment à la fontaine',
            'Par le trou de la serrure' => 'Le serment brisé',
            'La Montagne Marquée' => 'La vengeance',
            'Le jugement de Pressine' => 'La seconde malédiction',
            'La chasse au sanglier' => 'Le coup accidentel',
            'Une rencontre dans la clairière' => 'La rencontre à la fontaine de Soif',
            'Le serment' => 'La rencontre à la fontaine de Soif',
            'Un château élevé en une nuit' => 'La construction de Lusignan',
            'Le seigneur et la dame de Lusignan' => 'La construction de Lusignan',
            'Huit fils marqués' => 'La naissance des huit fils',
            "L'enfant des caves" => 'La naissance des huit fils',
            'Quatre navires quittent le Poitou' => 'Les grandes conquêtes',
            'Mélusine au rouet' => 'Les grandes conquêtes',
            'Le feu de Geoffroy' => "L'incendie de Malliers",
            'La cage brisée' => "L'incendie de Malliers",
            'Deux messagers' => "L'incendie de Malliers",
            'Ce que Raymondin vit' => 'Le trou de la serrure',
            'Une semaine de silence' => 'Le trou de la serrure',
            'La dénonciation' => 'La transformation',
            "Une fleur qui s'ouvre" => 'La transformation',
            'Autour des tours' => 'La transformation',
            'Liée par le souvenir' => 'La chute de Lusignan',
            'Un tas de pierres brisées' => 'La chute de Lusignan',
            'Jusqu\'au retour du prochain seigneur' => 'La chute de Lusignan',
        ];

        $acts = [
            [
                'name' => 'La jeunesse de Mélusine',
                'description' => "<p>Le mariage de Pressine avec le comte Elinas, le serment brisé, et la malédiction qu'elle jette sur ses filles — qui aboutira à la malédiction qui façonnera la vie de Mélusine elle-même.</p>",
                'chapters' => [
                    [
                        'name' => 'La malédiction de Pressine',
                        'description' => "Le comte Elinas trahit son serment envers Pressine, leurs filles se vengent, et Pressine jette la malédiction qui façonnera la vie de Mélusine elle-même.",
                        'scenes' => [
                            [
                                'name' => 'Une dame à la fontaine',
                                'contents' => "Il y a bien longtemps, dans la forêt de Bretagne, le comte Elinas d'Albanie partit à la chasse et perdit son chemin. Au cœur du bois profond, il trouva une fontaine où une dame était assise, peignant ses cheveux avec un peigne d'or. Elle se nommait Pressine, et elle était du sang des fées.\n\n« Madame, dit Elinas, jamais je n'ai vu si belle créature. Voulez-vous être mon épouse ? »\n\nPressine le regarda de ses yeux couleur d'eau profonde. « Je le veux bien, mon seigneur, à une condition. Vous ne devrez jamais me regarder lorsque je mettrai au monde nos enfants. Jurez-le. »\n\nElinas le jura sur les ossements de ses ancêtres.",
                            ],
                            [
                                'name' => 'Par le trou de la serrure',
                                'contents' => "Pressine lui donna trois filles en une seule naissance — Mélusine, Mélior et Palatine. Mais Elinas, entendant les cris venant de la chambre, s'inquiéta pour son épouse. Il s'approcha de la porte et regarda par le trou de la serrure.\n\nIl vit Pressine dans un bain d'argent, lavant le sang de ses filles nouveau-nées. Leurs regards se croisèrent à travers la fente.\n\n« Homme insensé, pleura-t-elle. Vous avez rompu votre serment. Je dois maintenant vous quitter, et nos filles porteront le poids de votre trahison. »\n\nElle rassembla les trois fillettes et disparut vers l'île cachée d'Avalon, où nul mortel ne pouvait la suivre.",
                            ],
                            [
                                'name' => 'La Montagne Marquée',
                                'contents' => "Les années passèrent. Sur Avalon, Pressine éleva ses filles dans les arts anciens — les chants du vent, le langage de la pierre, le tissage du destin.\n\nMélusine devint belle et fière. Lorsqu'elle apprit comment son père avait rompu son serment et chassé sa mère, son sang s'enflamma.\n\n« Il doit payer », dit-elle à Mélior et Palatine.\n\nCette nuit-là, les trois sœurs traversèrent la mer. Elles trouvèrent le comte Elinas endormi dans sa grand-salle. Elles l'enchaînèrent de chaînes d'argent féerique et le scellèrent vivant dans la Montagne Marquée, où la roche se resserre et où nulle lumière ne pénètre. Il y demeure encore, respirant la pierre, rêvant de l'épouse qu'il a perdue.",
                            ],
                            [
                                'name' => 'Le jugement de Pressine',
                                'contents' => "Pressine découvrit ce qu'avaient fait ses filles. Son visage, d'ordinaire chaleureux, devint froid comme l'eau d'hiver.\n\n« Vous avez enfreint la loi sacrée, dit-elle. Un enfant ne doit pas lever la main contre un parent. Pour cela, Mélusine, tu porteras la plus lourde des malédictions. »\n\nMélusine s'agenouilla. « Mère, je ne cherchais que justice pour vous. »\n\n« Voici ta justice. Chaque samedi, de la taille jusqu'aux pieds, tu deviendras serpent. Si un homme mortel t'épouse et ne te voit jamais un samedi, tu pourras vivre en femme et mourir en femme. Mais s'il te voit, et s'il en parle à un autre — tu deviendras serpent ailé à jamais, condamnée à errer sur la terre jusqu'au Jugement dernier. »\n\n« Et mes sœurs ? »\n\n« Mélior sera enfermée dans une tour jusqu'à ce qu'un chevalier assez brave pour la servir se présente. Palatine gardera le trésor de la montagne jusqu'à ce qu'un héros digne la réclame. »\n\nMélusine se releva. « Alors je trouverai un homme mortel qui gardera mon secret. Et je porterai des fils qui ébranleront le monde. »",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Mélusine et Raymondin',
                'description' => "<p>Raymondin tue accidentellement son oncle, se lie à Mélusine, et ensemble ils élèvent Lusignan, se marient et ont neuf fils — jusqu'à ce que son serment brisé la chasse du château pour toujours.</p>",
                'chapters' => [
                    [
                        'name' => 'La rencontre et le serment',
                        'description' => "Raymondin tue son oncle par accident, rencontre Mélusine à une fontaine de la forêt, et jure d'honorer sa seule condition — tandis qu'elle lui élève un château en une seule nuit.",
                        'scenes' => [
                            [
                                'name' => 'La chasse au sanglier',
                                'contents' => "Dans le comté de Poitou, un jeune chevalier nommé Raymondin chevauchait aux côtés de son oncle, le comte de Poitiers, chassant un grand sanglier. La bête fracassa les fourrés, et le comte éperonna son cheval en avant.\n\n« Maintenant, neveu ! Frappe ! »\n\nRaymondin lança sa lance. Le sanglier fit un écart. La lance transperça les côtes du comte, lui perçant le cœur.\n\nLe vieil homme tomba de cheval et ne bougea plus.\n\nRaymondin s'agenouilla près de lui, les mains rouges. « Mon oncle — je ne voulais pas — le sanglier — »\n\nMais les morts n'entendent plus.",
                            ],
                            [
                                'name' => 'Une rencontre dans la clairière',
                                'contents' => "Raymondin s'enfuit dans la forêt, l'esprit égaré. Il chevaucha jusqu'à ce que son cheval s'effondre, puis marcha jusqu'à ce que ses bottes s'usent. Il arriva enfin à une clairière où trois ruisseaux alimentaient une fontaine de marbre.\n\nIl tomba à genoux et but.\n\n« Ta peine est profonde, chevalier. »\n\nIl leva les yeux. Une femme se tenait devant lui, vêtue d'une robe d'étoffe d'argent. Ses cheveux tombaient jusqu'à sa taille, noirs comme l'aile d'un corbeau. Son visage était le plus beau qu'il eût jamais vu.\n\n« Je suis Mélusine, dit-elle. Je sais ce que tu as fait. C'était la faute du sanglier, non la tienne. La bête a trompé ton geste. »\n\n« Comment le savez-vous ? »\n\n« Je sais bien des choses. Je sais que si tu retournes à Poitiers, les hommes du comte te traiteront de meurtrier. Ils te pendront aux murailles. »\n\nRaymondin pleura. « Alors je suis déjà mort. »\n\n« Tu ne l'es pas. Je peux te donner des terres, un château, une richesse sans mesure. Je peux faire de toi le plus grand seigneur du Poitou. Si tu me prends pour épouse. »\n\n« Vous épouseriez un homme aux mains tachées de sang ? »\n\n« J'épouserais un homme au cœur sincère qui a commis une terrible erreur. »",
                            ],
                            [
                                'name' => 'Le serment',
                                'contents' => "Raymondin la regarda et n'y vit ni moquerie ni tromperie. « Je vous épouserai », dit-il.\n\nMélusine sourit. « Mais tu dois me jurer une chose. Chaque samedi, je dois être seule. Je dois m'enfermer dans ma chambre, et nul — pas même toi — ne doit poser les yeux sur moi. Jure-le, et je te donnerai tout. »\n\n« Je le jure sur mon âme. »\n\n« Alors viens, dit-elle en lui prenant la main. Laisse-moi te montrer où nous vivrons. »",
                            ],
                            [
                                'name' => 'Un château élevé en une nuit',
                                'contents' => "Mélusine mena Raymondin vers un promontoire sauvage dominant la rivière — un rocher escarpé couvert de ronces et d'épines.\n\n« Ici, dit-elle, je te bâtirai un château. »\n\nCette nuit-là, Raymondin dormit sous un chêne. Mélusine marcha jusqu'au rocher et leva les bras. Elle invoqua des puissances plus anciennes que la croix — les esprits de la terre, les murmures dans la pierre.\n\nToute la nuit, le sol trembla. Des blocs de marbre blanc s'élevèrent du sol. Des tours poussèrent comme des arbres. Les murs se tissèrent entre eux. À l'aube, le château de Lusignan se dressait achevé — portes, remparts, grande salle et chapelle.\n\nRaymondin s'éveilla et le vit. Il tomba à genoux.\n\n« C'est un miracle », murmura-t-il.\n\n« C'est un foyer », dit Mélusine.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'Les fils de Lusignan',
                        'description' => "Mélusine épouse Raymondin, met au monde huit fils marqués et un neuvième caché, et voit quatre d'entre eux conquérir couronnes et titres à travers l'Europe et l'Orient.",
                        'scenes' => [
                            [
                                'name' => 'Le seigneur et la dame de Lusignan',
                                'contents' => "L'évêque de Poitiers les maria dans la chapelle du château. La beauté de Mélusine était célébrée dans toutes les cours de France. Sa générosité était sans limite — elle offrait de riches présents à chaque invité, et nul ne repartait le ventre vide.\n\nRaymondin fut proclamé seigneur de Lusignan.\n\nMais chaque samedi, Mélusine se retirait dans la chambre de sa tour. Elle barrait la porte de fer et ne parlait à personne. Les serviteurs chuchotaient. Les servantes de cuisine se signaient. Raymondin ne disait rien.",
                            ],
                            [
                                'name' => 'Huit fils marqués',
                                'contents' => "Avec le temps, Mélusine donna à Raymondin huit fils. Chacun vint au monde avec une marque étrange, signe du sang féerique de leur mère.\n\nUrien, l'aîné, avait une oreille rouge, unique — l'oreille d'un loup. Il deviendrait roi à Chypre.\n\nGuyon, le second, avait l'œil gauche qui luisait comme celui d'un chat dans l'obscurité. Il tuerait un géant au Luxembourg.\n\nAntoine, le troisième, portait sur la joue une marque de griffe où l'ongle de sa mère l'avait effleuré dans la nuit. Il conquerrait la moitié de l'Arménie.\n\nReynaud, le quatrième, avait un œil placé plus haut que l'autre. Il deviendrait connétable de France.\n\nGeoffroy, le cinquième — appelé Geoffroy la Grand'Dent — portait une défense d'os saillant de sa mâchoire. Son tempérament était une fournaise. Il incendierait une abbaye et tuerait son propre frère.\n\nFromont, le sixième, portait une marque rouge en travers du nez, comme la capuche d'un moine. Lui seul était doux. Il entra dans les ordres et pria pour les péchés de sa famille.\n\nThierry, le septième, avait un œil rouge comme le sang. Il deviendrait seigneur de Vouvant.\n\nRaymondet, le huitième et dernier, avait trois yeux — deux à leur place ordinaire et un au-dessus du nez. Sa mère l'aimait plus que tous les autres.",
                            ],
                            [
                                'name' => "L'enfant des caves",
                                'contents' => "Mais il y avait un neuvième fils, né en secret, que Mélusine cachait dans les caves les plus profondes de Lusignan. Il se nommait Horrible.\n\nIl ne portait aucune marque sur le visage — son corps entier était la marque. Sa peau était écailleuse comme celle d'un lézard. Ses doigts se terminaient en griffes. Ses dents étaient des aiguilles. Il grandit dans l'obscurité, se nourrissant de viande crue, s'exprimant en sifflements.\n\nMélusine lui rendait visite la nuit. « Tu es mon fils, murmurait-elle, mais tu ne peux marcher parmi les hommes. Tu les terrifierais. »\n\nHorrible rongeait un os et ne disait rien.",
                            ],
                            [
                                'name' => 'Quatre navires quittent le Poitou',
                                'contents' => "Le même mois, quatre navires quittèrent le port du Poitou.\n\nUrien cingla vers l'est, vers l'île de Chypre. Les Sarrasins tenaient la côte, mais Urien brûla leur flotte et rompit leurs lignes en une seule matinée. Le roi de Chypre lui donna sa fille, et Urien porta une couronne.\n\nGuyon chevaucha vers le nord, jusqu'au Luxembourg. Un géant nommé Maldichet dévorait les enfants des villages. Guyon le rencontra sur un pont de pierre au-dessus de la Sûre. Ils combattirent trois heures durant. Le géant brisa le bouclier de Guyon. Guyon trancha les jarrets du géant, puis lui enfonça son épée sous les côtes. Le duc de Luxembourg l'adouba sur le champ de bataille.\n\nAntoine cingla vers l'est, vers l'Arménie. Le Sultan assiégeait la capitale depuis sept ans. Antoine chevaucha seul devant les murailles et défia le champion du Sultan. Un seul coup — de la selle jusqu'au menton. Le roi d'Arménie lui offrit la moitié de son royaume et la main de sa fille.\n\nReynaud chevaucha jusqu'à Paris, à la cour du roi de France. Il rejoignit l'armée royale, combattit dans trois campagnes, et ne perdit jamais une escarmouche. Le roi le fit connétable de France, le plus haut chevalier du royaume.",
                            ],
                            [
                                'name' => 'Mélusine au rouet',
                                'contents' => "Pendant ce temps, Mélusine, assise dans sa tour de Lusignan, filait un fil d'argent. Elle voyait chacun de ses fils dans le fil — Urien sur son trône, Guyon près du corps du géant, Antoine essuyant son épée, Reynaud agenouillé devant le roi.\n\n« Quatre sont sains et saufs, dit-elle. Mais le cinquième — »\n\nElle vit le feu.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'Le poison et le trou de la serrure',
                        'description' => "La rage de Geoffroy embrase une abbaye et tue son frère, Horrible s'échappe des caves, et le doute semé par le comte de Forez pousse Raymondin à espionner son épouse.",
                        'scenes' => [
                            [
                                'name' => 'Le feu de Geoffroy',
                                'contents' => "Geoffroy était resté au Poitou. Son tempérament croissait avec sa force. Lorsque l'abbé de Malliers lui refusa le passage à travers les terres de chasse de l'abbaye, le sang de Geoffroy s'enflamma.\n\n« Me refuser ? rugit-il. Je suis un fils de Lusignan. Nul moine ne barrera mon chemin. »\n\nCette nuit-là, il revint avec des torches. Il mit le feu au toit de l'abbaye. Les flammes se propagèrent de la chapelle au dortoir puis à la bibliothèque. Les moines couraient en hurlant dans la nuit, leurs robes en feu.\n\nÀ l'intérieur de l'abbaye, venu prier, se trouvait Fromont — le propre frère de Geoffroy, le doux sixième fils.\n\nFromont mourut dans les flammes, sa coule de moine brûlant sur son visage.",
                            ],
                            [
                                'name' => 'La cage brisée',
                                'contents' => "Dans les caves de Lusignan, Horrible sentit le feu. Il le sentit à travers la pierre. Il entendit les cris dans son sang.\n\nIl brisa les barreaux de fer de sa cage et se hissa dans le château.\n\nCette nuit-là, trois serviteurs disparurent. Au matin, on ne trouva que des ossements.\n\nMélusine trouva Horrible dans les cuisines, accroupi sur un quatrième corps. Elle ne le gronda pas.\n\n« Tu ne peux rester ici, dit-elle doucement. Tu vas tout détruire. »\n\nElle le conduisit vers un tunnel secret sous le château, un passage qui s'ouvrait sur les bois profonds. « Va vers le nord, dit-elle. Trouve les montagnes. Vis dans les grottes. Je t'enverrai de la nourriture. »\n\nHorrible la regarda de ses yeux jaunes. « Mère », siffla-t-il. Ce fut le seul mot qu'il prononça jamais.\n\nIl disparut dans l'obscurité.",
                            ],
                            [
                                'name' => 'Deux messagers',
                                'contents' => "Deux messagers arrivèrent à Lusignan le même jour.\n\nLe premier apportait des nouvelles de Malliers. « Geoffroy a incendié l'abbaye de Malliers. Tous les moines sont morts. Et Fromont — votre fils Fromont — était parmi eux. »\n\nLe visage de Raymondin devint gris. « Mon fils a tué son frère ? »\n\n« Le feu l'a emporté, mon seigneur. Par la main de Geoffroy ou par les flammes, nul ne le sait. »\n\nLe second messager était le comte de Forez, un noble jaloux qui depuis longtemps enviait l'ascension de Raymondin.\n\n« Tragique nouvelle, mon seigneur, dit Forez en versant du vin. Mais je me demande — n'avez-vous jamais douté des étranges habitudes de votre épouse ? Ces samedis secrets ? Cette richesse soudaine ? Ces fils nés avec des griffes et des défenses ? »\n\n« Tenez votre langue », dit Raymondin.\n\n« Je ne pense qu'à vous, mon ami. Quelle sorte de femme met au monde des monstres ? »",
                            ],
                            [
                                'name' => 'Ce que Raymondin vit',
                                'contents' => "Ce samedi-là, Raymondin ne put trouver le repos. Il arpentait les couloirs de son château comme un fantôme. Les paroles de Forez résonnaient dans son crâne.\n\n*Quelle sorte de femme met au monde des monstres ?*\n\nIl se retrouva devant la chambre de Mélusine. Un filet de lumière de bougie filtrait par le trou de la serrure.\n\nIl s'agenouilla.\n\nIl regarda.\n\nÀ l'intérieur, Mélusine était assise dans un grand bain de marbre rempli d'eau fumante. De la taille jusqu'à la tête, c'était la femme qu'il avait épousée — ses épaules blanches, ses cheveux sombres, son visage calme comme l'ivoire sculpté. Mais de la taille jusqu'aux pieds, son corps était celui d'un serpent — une longue et épaisse queue couverte d'écailles d'argent et de bleu, s'enroulant et se déroulant dans l'eau comme une chose vivante.\n\nLe cœur de Raymondin s'arrêta. Il tomba en arrière, une main sur la bouche.\n\nIl s'éloigna sans un bruit.",
                            ],
                            [
                                'name' => 'Une semaine de silence',
                                'contents' => "Pendant une semaine, Raymondin ne dit rien. Il s'asseyait à dîner en face de Mélusine et ne pouvait soutenir son regard. Il touchait sa main et sentait un frisson de peur.\n\nMélusine l'observait. Elle savait.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'La rupture',
                        'description' => "La nouvelle de la mort d'Urien arrive, Raymondin dénonce Mélusine devant toute la cour, et elle se transforme en serpent ailé et s'envole de Lusignan pour toujours.",
                        'scenes' => [
                            [
                                'name' => "La mort d'Urien",
                                'contents' => "Le troisième messager arriva sept jours plus tard. Urien était mort à Chypre. Les Sarrasins l'avaient pris en embuscade dans un col de montagne. Il était mort l'épée à la main, couvert de blessures, mais n'était tombé qu'après avoir mis ses hommes à l'abri.\n\nRaymondin reçut la nouvelle dans la grande salle, entouré de ses chevaliers.",
                            ],
                            [
                                'name' => 'La dénonciation',
                                'contents' => "Il se leva. Son visage était pâle comme l'os.\n\n« J'ai gardé un secret trop longtemps, dit-il. J'ai protégé un démon dans ma propre maison. »\n\n« Raymondin — » dit Mélusine, entrant dans la salle. « Ne dis rien. »\n\n« Mon épouse est un serpent ! » cria Raymondin en la désignant du doigt. « Chaque samedi, elle devient serpent de la taille aux pieds. Je l'ai vue de mes propres yeux. Nos enfants — ces monstres — sont la progéniture d'une bête féerique ! »\n\nLa salle explosa. Les chevaliers se signaient. Les dames poussaient des cris.",
                            ],
                            [
                                'name' => "Une fleur qui s'ouvre",
                                'contents' => "Mélusine se tenait au centre de la salle. Elle ne pleura pas. Elle ne s'emporta pas. Son visage ne portait qu'une douleur ancienne et sans fond.\n\n« Tu as rompu ton serment, dit-elle. Tu as regardé. Et maintenant tu as parlé. »\n\n« Ton fils a tué son frère ! cria Raymondin. Ton autre fils déchire des hommes dans le noir ! Tu es maudite, femme, et tu m'as maudit avec toi ! »\n\n« Je ne t'ai jamais maudit. Je t'ai aimé. Je t'ai tout donné. »\n\nElle écarta les bras. Sa forme commença à changer — non dans la douleur, mais comme une fleur qui s'ouvre. Sa robe se dissipa en brume. Des ailes d'argent et de blanc se déployèrent de ses épaules, assez larges pour toucher les murs. Ses jambes se fondirent, s'allongèrent, devinrent une queue de serpent. Des écailles de nacre et de saphir gravirent sa taille, ses côtes, sa gorge.\n\nElle s'éleva dans les airs — femme-dragon, belle et terrible.\n\n« Je t'ai aimé, dit-elle. J'ai aimé nos fils. Je n'ai aimé personne d'autre en toutes les longues années de ma vie. »\n\nElle fit un tour de la grande salle, puis s'envola par la fenêtre dans la nuit.",
                            ],
                            [
                                'name' => 'Autour des tours',
                                'contents' => "Mélusine survola trois fois le château de Lusignan. Son cri n'était pas un hurlement — c'était un son semblable à une harpe qui se brise, à la glace qui craque sur une rivière gelée. On l'entendit dans chaque village à une journée de cheval.\n\nAu premier passage, les tours pleurèrent du mortier.\n\nAu deuxième passage, les portes gémirent et se fendirent.\n\nAu troisième passage, elle s'éleva dans les nuages et disparut.",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Après Mélusine',
                'description' => "<p>Après le départ de Mélusine, Raymondin meurt en pénitent, Horrible grandit sauvage dans les montagnes, et les fils de Lusignan connaissent leur destin un par un jusqu'à ce que la lignée et le château sombrent dans la ruine et la légende.</p>",
                'chapters' => [
                    [
                        'name' => 'La ruine de Lusignan',
                        'description' => "La hantise de Mélusine, la mort pénitente de Raymondin, l'exil d'Horrible, la chute des fils un par un, et la lente ruine du château dans la légende.",
                        'scenes' => [
                            [
                                'name' => 'Liée par le souvenir',
                                'contents' => "Mélusine ne partit pas tout à fait.\n\nLa nuit précédant la mort d'un seigneur de Lusignan, elle apparaît sur la plus haute tour, vêtue de blanc, ses cheveux dénoués au vent. Elle pleure, et ses pleurs résonnent comme l'eau courant sur la pierre.\n\nElle reste liée au château — liée par l'amour, par le souvenir, par les fils qu'elle y a mis au monde.",
                            ],
                            [
                                'name' => 'Le pénitent',
                                'contents' => "Raymondin quitta Lusignan cette même année. Il erra sur les routes du Poitou, vêtu d'une cape élimée, pénitent sans repos. Il visita chaque église, chaque sanctuaire, chaque grotte d'ermite.\n\nIl mourut sept ans plus tard dans une cabane de bois, seul hormis un prêtre.\n\nSon dernier mot fut son nom à elle.",
                            ],
                            [
                                'name' => 'Les Ardennes',
                                'contents' => "Loin au nord, dans les grottes des Ardennes, Horrible grandit, immense et sauvage. Il mangeait des cerfs crus et buvait aux ruisseaux de montagne. Parfois, la nuit, il regardait vers le sud, vers Lusignan, et sifflait.\n\nIl ne revit jamais sa mère.",
                            ],
                            [
                                'name' => 'Un par un',
                                'contents' => "Les morts vinrent l'une après l'autre.\n\nGuyon tomba au Luxembourg, défendant le duc qui l'avait honoré. Un carreau d'arbalète en travers de la gorge.\n\nAntoine mourut en Arménie, son royaume brûlant autour de lui lorsque le Sultan revint avec une armée plus nombreuse.\n\nReynaud fut tué lors d'une campagne française contre les Anglais. Une flèche dans l'œil — le plus haut des deux.\n\nGeoffroy ne fut jamais revu après la mort de son père. Certains disent qu'il cingla vers la Terre sainte. D'autres disent qu'il suivit sa mère dans l'autre monde. D'autres encore disent qu'il arpente toujours les routes du Poitou, géant à la défense, cherchant un combat qu'il ne peut gagner.\n\nThierry vécut le plus longtemps. Il tint Vouvant quarante ans et mourut dans son lit, entouré de ses enfants. Son œil rouge se ferma en paix.\n\nRaymondet — celui aux trois yeux, le bien-aimé — entra dans un monastère et passa sa vie à copier des livres. Nul ne sait quand il mourut.",
                            ],
                            [
                                'name' => 'Récits des cavernes',
                                'contents' => "Et Horrible ? Dans les grottes du nord, les chasseurs racontent l'histoire d'une bête aux écailles et aux yeux jaunes. On dit qu'elle a la forme d'un homme mais la faim d'un serpent. On dit qu'elle n'attaque jamais sans être provoquée.\n\nIls ignorent que c'est le dernier fils de Mélusine.",
                            ],
                            [
                                'name' => 'Un tas de pierres brisées',
                                'contents' => "Le château passa de main en main. La lignée de Lusignan se fondit dans d'autres maisons — la maison de Chypre, le royaume de Jérusalem, les cours de France et d'Angleterre. Le sang de Mélusine coulait dans les rois, mais nul roi ne se souvenait de son nom.\n\nLe château s'effondra. Les tours tombèrent. La grande salle où Mélusine avait déployé ses ailes devint un tas de pierres brisées.",
                            ],
                            [
                                'name' => 'Jusqu\'au retour du prochain seigneur',
                                'contents' => "Aujourd'hui encore, quand le vent souffle sur les ruines de Lusignan, on entend une femme pleurer.\n\nEt certaines nuits — quand la lune est pleine et que les nuages courent bas — un serpent ailé survole les tours brisées. Une fois. Deux fois. Trois fois.\n\nPuis elle disparaît.\n\nJusqu'au retour du prochain seigneur.",
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
        // Attribute definitions. `applies_to` picks which entry types show each attribute;
        // "Reputation" is deliberately shared by characters and organizations to exercise
        // the applies-to filtering. `position` is set by hand (the creating hook is off).
        $hairColor = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Couleur de cheveux'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 1],
        );

        $frescoes = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Fresques'],
            ['applies_to' => [CodexEntryType::Location], 'position' => 2],
        );

        $fortunes = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Fortunes'],
            ['applies_to' => [CodexEntryType::Organization], 'position' => 3],
        );

        $reputation = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Réputation'],
            ['applies_to' => [CodexEntryType::Character, CodexEntryType::Organization], 'position' => 4],
        );

        // --- Characters ---

        $melusine = $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Mélusine',
            '<p>Une fée de la forêt profonde, <strong>maudite</strong> à prendre une forme de serpent de la taille aux pieds chaque samedi. Épouse de Raymondin et mère des neuf fils de Lusignan.</p>',
            ['Melusina', 'La Dame Serpent', 'Dame de Lusignan'],
            ['Fée', 'Protagoniste', 'Maudite'],
        );

        // Mélusine's hair over time: raven black by default, curse-touched after Pressine's
        // judgment, and loose and wild once she takes her winged serpent form.
        $this->seedPeriods($melusine, $hairColor, [
            [null, "Noir corbeau, tombant jusqu'à la taille"],
            ['La seconde malédiction', "Noir corbeau, strié d'argent le samedi"],
            ['La transformation', 'Sauvages et dénoués autour de ses ailes'],
        ], $eventsByTitle);

        $this->seedPeriods($melusine, $reputation, [
            [null, 'Une fée inconnue de la fontaine des bois'],
            ['La construction de Lusignan', 'La dame bien-aimée et généreuse de Lusignan'],
            ['La transformation', 'Dénoncée devant la cour comme un démon-serpent'],
        ], $eventsByTitle);

        $raymondin = $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Raymondin de Lusignan',
            "<p>Un jeune chevalier du Poitou qui tue accidentellement son oncle, épouse Mélusine, et devient le premier <em>seigneur de Lusignan</em> — jusqu'à ce que son serment brisé les perde tous deux.</p>",
            ['Raymond', 'Seigneur de Lusignan'],
            ['Chevalier', 'Protagoniste'],
        );

        $this->seedPeriods($raymondin, $hairColor, [
            [null, 'Brun châtain'],
            ['La transformation', 'Grisonnant de chagrin'],
        ], $eventsByTitle);

        $this->seedPeriods($raymondin, $reputation, [
            [null, 'Un neveu de peu d\'importance du comte de Poitiers'],
            ['La construction de Lusignan', 'Le seigneur montant de Lusignan'],
            ['La transformation', 'Un veuf brisé et pénitent'],
        ], $eventsByTitle);

        // --- Location ---

        $castle = $this->seedEntry(
            $project,
            CodexEntryType::Location,
            'Le château de Lusignan',
            '<p>Le grand château de marbre blanc que Mélusine éleva en une <strong>seule nuit</strong> sur un promontoire épineux au-dessus de la rivière.</p>',
            ['Lusignan'],
            ['Château', 'Poitou'],
        );

        // The painted walls of the great hall, from bare rock to fresh marble to slow ruin.
        $this->seedPeriods($castle, $frescoes, [
            [null, 'Aucune — un promontoire de roche nue et épineuse'],
            ['La construction de Lusignan', 'Murs de marbre blanc, fraîchement élevés et sans ornement'],
            ['La transformation', 'Murs fissurés pleurant du mortier là où Mélusine a tourné'],
        ], $eventsByTitle);

        // --- Organization ---

        $house = $this->seedEntry(
            $project,
            CodexEntryType::Organization,
            'La Maison de Lusignan',
            "<p>La lignée noble fondée par Mélusine et Raymondin, dont les fils conquièrent des couronnes à travers l'Europe et l'Orient avant que la maison ne se fonde dans d'autres dynasties.</p>",
            ['Les Lusignan'],
            ['Maison noble'],
        );

        $this->seedPeriods($house, $fortunes, [
            [null, 'Pas encore fondée'],
            ['La construction de Lusignan', 'Seigneurs nouvellement établis d\'un château élevé par magie'],
            ['Les grandes conquêtes', "Couronnes et titres conquis à travers l'Europe et l'Orient"],
            ['La chute de Lusignan', "Fondue dans d'autres maisons ; le château tombé en ruine"],
        ], $eventsByTitle);

        $this->seedPeriods($house, $reputation, [
            [null, 'Un nom inconnu'],
            ['Les grandes conquêtes', 'Renommée dans toute la chrétienté'],
        ], $eventsByTitle);
    }

    /**
     * Create (idempotently) one Codex entry with its aliases and tags.
     *
     * Aliases are firstOrCreate'd children; tags are firstOrCreate'd once per project name
     * and attached without detaching, so entries can share tags (e.g. "Protagoniste").
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

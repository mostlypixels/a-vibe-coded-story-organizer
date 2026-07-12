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

class MelusineSeederIt extends Seeder
{
    /**
     * Seed a sample "Il Romanzo di Melusina" project (Italian) with plotlines and events.
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
            'name' => 'Il Romanzo di Melusina',
            'language' => BookLanguage::Italian,
            'description' => <<<'HTML'
                <p>Una leggenda medievale della fata <strong>Melusina</strong>, della sua maledizione, del suo matrimonio con <em>Raimondino di Lusignano</em>, e del destino dei loro nove figli.</p>
                <h3>I fili della narrazione</h3>
                <ul>
                    <li>La maledizione che Pressina getta sulle sue figlie.</li>
                    <li>Il patto e il matrimonio di Melusina e Raimondino.</li>
                    <li>Le conquiste e le tragedie dei figli di Lusignano.</li>
                </ul>
                HTML,
        ]);

        $mainPlotline = $project->plotlines()->firstWhere('is_main', true)
            ?? Plotline::create([
                'project_id' => $project->id,
                'name' => 'Trama principale',
                'is_main' => true,
                'color' => PlotlineColors::PRESETS[0], // red-500
            ]);

        $curseOfPressine = Plotline::create([
            'project_id' => $project->id,
            'name' => 'La maledizione di Pressina',
            'description' => "<p>Il matrimonio di Pressina con il conte Elinas, il <strong>giuramento tradito</strong>, e la maledizione che ella getta sulle sue figlie.</p>",
            'color' => PlotlineColors::PRESETS[8], // green-500
        ]);

        $melusineAndRaymondin = Plotline::create([
            'project_id' => $project->id,
            'name' => 'Melusina e Raimondino',
            'description' => "<p>La corte, il matrimonio, e il <em>tramonto</em> finale di Melusina e Raimondino di Lusignano.</p>",
            'color' => PlotlineColors::PRESETS[16], // sky-500
        ]);

        $sonsOfLusignan = Plotline::create([
            'project_id' => $project->id,
            'name' => 'I figli di Lusignano',
            'description' => "<p>Le conquiste, i trionfi e le tragedie dei <strong>nove figli</strong> di Melusina e Raimondino.</p>",
            'color' => PlotlineColors::PRESETS[24], // purple-500
        ]);

        foreach ([
            ['title' => 'Inizio', 'event_datetime' => '0001-01-01 00:00:00'],
            ['title' => 'Fine', 'event_datetime' => '3000-01-01 00:00:00'],
        ] as $bookend) {
            $bookendEvent = $project->events()->firstOrCreate(
                ['title' => $bookend['title']],
                $bookend + ['is_fixed' => true],
            );
            $bookendEvent->plotlines()->syncWithoutDetaching($mainPlotline->id);
        }

        $events = [
            [
                'title' => 'Il giuramento alla fontana',
                'description' => 'Il conte Elinas incontra Pressina a una fontana nel bosco e giura di non guardarla mai mentre partorisce.',
                'event_datetime' => '1000-03-01 12:00:00',
                'plotlines' => [$curseOfPressine, $mainPlotline],
            ],
            [
                'title' => 'Il giuramento tradito',
                'description' => 'Elinas spia dal buco della serratura e vede Pressina che bagna le sue figlie appena nate. Pressina fugge verso Avalon.',
                'event_datetime' => '1001-01-10 09:00:00',
                'plotlines' => [$curseOfPressine],
            ],
            [
                'title' => 'La vendetta',
                'description' => 'Melusina, Melior e Palatina sigillano il conte Elinas vivo nella Montagna Marchiata.',
                'event_datetime' => '1016-06-15 22:00:00',
                'plotlines' => [$curseOfPressine],
            ],
            [
                'title' => 'La seconda maledizione',
                'description' => 'Pressina maledice Melusina a diventare un serpente dalla cintola ai piedi ogni sabato.',
                'event_datetime' => '1016-06-20 08:00:00',
                'plotlines' => [$curseOfPressine, $melusineAndRaymondin],
            ],
            [
                'title' => 'Il colpo accidentale',
                'description' => 'Cacciando un cinghiale, Raimondino uccide accidentalmente suo zio, il conte di Poitiers, con una lancia sfuggita di mano.',
                'event_datetime' => '1035-09-02 07:30:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'L\'incontro alla fontana della Sete',
                'description' => 'Raimondino fugge nel bosco e incontra Melusina, che gli offre ricchezze e matrimonio in cambio del suo silenzio.',
                'event_datetime' => '1035-09-03 20:00:00',
                'plotlines' => [$melusineAndRaymondin],
            ],
            [
                'title' => 'La costruzione di Lusignano',
                'description' => 'In una sola notte, Melusina eleva il castello di Lusignano su un promontorio spinoso.',
                'event_datetime' => '1035-10-01 05:00:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'La nascita degli otto figli',
                'description' => 'Melusina partorisce a Raimondino otto figli, ciascuno segnato dal suo sangue fatato, e nasconde segretamente un nono, Orribile, nelle cantine.',
                'event_datetime' => '1042-04-12 03:00:00',
                'plotlines' => [$melusineAndRaymondin, $sonsOfLusignan],
            ],
            [
                'title' => 'Le grandi conquiste',
                'description' => "Urien, Guyon, Antoine e Reynaud partono tutti nello stesso mese per conquistare corone e titoli attraverso l'Europa e l'Oriente.",
                'event_datetime' => '1060-05-01 06:00:00',
                'plotlines' => [$sonsOfLusignan],
            ],
            [
                'title' => 'L\'incendio di Malliers',
                'description' => 'Geoffroy, preso da ira, incendia l\'abbazia di Malliers, uccidendo il suo dolce fratello Fromont, che si trovava lì in preghiera.',
                'event_datetime' => '1061-11-30 23:00:00',
                'plotlines' => [$sonsOfLusignan],
            ],
            [
                'title' => 'Il buco della serratura',
                'description' => 'Divorato dal dubbio, Raimondino spia dal buco della serratura della camera di Melusina e vede la sua forma di serpente.',
                'event_datetime' => '1061-12-07 21:00:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'La trasformazione',
                'description' => 'Dopo che Raimondino l\'ha denunciata davanti a tutta la corte, Melusina si trasforma in un serpente alato e si alza in volo da Lusignano per sempre.',
                'event_datetime' => '1061-12-14 19:00:00',
                'plotlines' => [$melusineAndRaymondin, $mainPlotline],
            ],
            [
                'title' => 'La caduta di Lusignano',
                'description' => 'Generazioni dopo, la stirpe di Lusignano si estingue e il castello cade in rovina, anche se si dice che Melusina ancora sorvoli le sue torri.',
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
            'Una dama alla fontana' => 'Il giuramento alla fontana',
            'Attraverso il buco della serratura' => 'Il giuramento tradito',
            'La Montagna Marchiata' => 'La vendetta',
            'Il giudizio di Pressina' => 'La seconda maledizione',
            'La caccia al cinghiale' => 'Il colpo accidentale',
            'Un incontro nella radura' => 'L\'incontro alla fontana della Sete',
            'Il giuramento' => 'L\'incontro alla fontana della Sete',
            'Un castello elevato in una notte' => 'La costruzione di Lusignano',
            'Il signore e la signora di Lusignano' => 'La costruzione di Lusignano',
            'Otto figli segnati' => 'La nascita degli otto figli',
            'Il fanciullo delle cantine' => 'La nascita degli otto figli',
            'Quattro navi lasciano il Poitou' => 'Le grandi conquiste',
            'Melusina al telaio' => 'Le grandi conquiste',
            'Il fuoco di Geoffroy' => 'L\'incendio di Malliers',
            'La gabbia spezzata' => 'L\'incendio di Malliers',
            'Due messaggeri' => 'L\'incendio di Malliers',
            'Ciò che Raimondino vide' => 'Il buco della serratura',
            'Una settimana di silenzio' => 'Il buco della serratura',
            'La denuncia' => 'La trasformazione',
            'Un fiore che si apre' => 'La trasformazione',
            'Intorno alle torri' => 'La trasformazione',
            'Legata dal ricordo' => 'La caduta di Lusignano',
            'Un cumulo di pietre rotte' => 'La caduta di Lusignano',
            'Fino al ritorno del prossimo signore' => 'La caduta di Lusignano',
        ];

        $acts = [
            [
                'name' => 'La giovinezza di Melusina',
                'description' => "<p>Il matrimonio di Pressina con il conte Elinas, il giuramento tradito, e la maledizione che ella getta sulle sue figlie — che culminerà nella maledizione che plasmerà la vita di Melusina stessa.</p>",
                'chapters' => [
                    [
                        'name' => 'La maledizione di Pressina',
                        'description' => 'Il conte Elinas tradisce il suo giuramento a Pressina, le loro figlie si vendicano, e Pressina getta la maledizione che plasmerà la vita di Melusina stessa.',
                        'scenes' => [
                            [
                                'name' => 'Una dama alla fontana',
                                'contents' => "Molto tempo fa, nella foresta di Bretagna, il conte Elinas di Albania partì a caccia e si perse. Nel profondo del bosco trovò una fontana dove una dama era seduta, pettinandosi i capelli con un pettine d\'oro. Si chiamava Pressina, ed era del sangue delle fate.\n\n« Signora, disse Elinas, non ho mai visto una creatura così bella. Volete essere mia moglie? »\n\nPressina lo guardò con occhi del colore dell\'acqua profonda. « Lo voglio, mio signore, a una condizione. Non dovrete mai guardarmi quando partorisco i nostri figli. Giuratelo. »\n\nElinas giurò sulle ossa dei suoi antenati.",
                            ],
                            [
                                'name' => 'Attraverso il buco della serratura',
                                'contents' => "Pressina gli partorì tre figlie in una sola gravidanza — Melusina, Melior e Palatina. Ma Elinas, sentendo le grida dalla camera, si preoccupò per sua moglie. Si avvicinò alla porta e spiò dal buco della serratura.\n\nVide Pressina in un bagno d\'argento, che lavava il sangue dalle sue figlie appena nate. I loro sguardi si incrociarono attraverso la fessura.\n\n« Uomo insensato, pianse ella. Avete rotto il vostro giuramento. Devo ora lasciarvi, e le nostre figlie porteranno il peso del vostro tradimento. »\n\nRaccelse le tre bambine e sparì verso l\'isola nascosta di Avalon, dove nessun mortale poteva seguirla.",
                            ],
                            [
                                'name' => 'La Montagna Marchiata',
                                'contents' => "Gli anni passarono. Su Avalon, Pressina allevò le sue figlie nelle arti antiche — i canti del vento, il linguaggio della pietra, il tessere del destino.\n\nMelusina divenne bella e superba. Quando apprese come suo padre aveva rotto il suo giuramento e aveva scacciato sua madre, il suo sangue si infiammò.\n\n« Deve pagare, » disse a Melior e Palatina.\n\nQuella notte, le tre sorelle attraversarono il mare. Trovarono il conte Elinas addormentato nella sua sala. Lo incatenarono con catene d\'argento fatato e lo sigillarono vivo nella Montagna Marchiata, dove la roccia si stringe e nessuna luce penetra. Lì rimane ancora, respirando pietra, sognando la moglie che ha perso.",
                            ],
                            [
                                'name' => 'Il giudizio di Pressina',
                                'contents' => "Pressina scoprì ciò che avevano fatto le sue figlie. Il suo volto, solitamente caldo, divenne freddo come l\'acqua d\'inverno.\n\n« Avete infranto la legge sacra, disse ella. Un figlio non deve alzare la mano contro un genitore. Per questo, Melusina, porterai la più pesante delle maledizioni. »\n\nMelusina si inginocchiò. « Madre, cercavo solo giustizia per voi. »\n\n« Ecco la tua giustizia. Ogni sabato, dalla cintola ai piedi, diventerai serpente. Se un uomo mortale ti sposerà e non ti vedrà mai di sabato, potrai vivere da donna e morire da donna. Ma se ti vedrà, e se ne parlerà a un altro — diventerai un serpente alato per sempre, condannata a errare sulla terra fino al Giudizio Finale. »\n\n« E le mie sorelle? »\n\n« Melior sarà rinchiusa in una torre fino a quando un cavaliere abbastanza coraggioso da servirla non si presenterà. Palatina custodirà il tesoro della montagna fino a quando un eroe degno non la rivendicherà. »\n\nMelusina si alzò. « Allora troverò un uomo mortale che custodirà il mio segreto. E partorirò figli che scuoteranno il mondo. »",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Melusina e Raimondino',
                'description' => "<p>Raimondino uccide accidentalmente suo zio, si lega a Melusina, e insieme elevano Lusignano, si sposano e partoriscono nove figli — fino a quando il suo giuramento tradito la scaccia dal castello per sempre.</p>",
                'chapters' => [
                    [
                        'name' => 'L\'incontro e il giuramento',
                        'description' => 'Raimondino uccide suo zio per sbaglio, incontra Melusina a una fontana nel bosco, e giura di onorare la sua unica condizione — mentre ella gli eleva un castello in una sola notte.',
                        'scenes' => [
                            [
                                'name' => 'La caccia al cinghiale',
                                'contents' => "Nella contea di Poitou, un giovane cavaliere di nome Raimondino cavalcava accanto a suo zio, il conte di Poitiers, cacciando un grande cinghiale. La bestia si fracassò tra i rami, e il conte spronò il suo cavallo in avanti.\n\n« Adesso, nipote! Colpisci! »\n\nRaimondino scagliò la sua lancia. Il cinghiale si scostò. La lancia trapassò i fianchi del conte, perforandogli il cuore.\n\nIl vecchio uomo cadde da cavallo e non si mosse più.\n\nRaimondino si inginocchiò accanto a lui, le mani rosse. « Zio — non volevo — il cinghiale — »\n\nMa i morti non sentono più.",
                            ],
                            [
                                'name' => 'Un incontro nella radura',
                                'contents' => "Raimondino fuggì nel bosco, la mente confusa. Cavalcò fino a quando il suo cavallo crollò, poi camminò fino a quando gli stivali si consumarono. Infine arrivò a una radura dove tre ruscelli alimentavano una fontana di marmo.\n\nCadde in ginocchio e bevve.\n\n« Il tuo dolore è profondo, cavaliere. »\n\nAlzò lo sguardo. Una donna stava davanti a lui, vestita di stoffa d\'argento. I suoi capelli le cadevano fino alla cintola, neri come l\'ala di un corvo. Il suo volto era il più bello che avesse mai visto.\n\n« Sono Melusina, disse ella. So ciò che hai fatto. Era colpa del cinghiale, non tua. La bestia ha ingannato il tuo gesto. »\n\n« Come potete saperlo? »\n\n« So molte cose. So che se torni a Poitiers, gli uomini del conte ti tratteranno da assassino. Ti appenderanno alle mura. »\n\nRaimondino pianse. « Allora sono già morto. »\n\n« Non lo sei. Posso darti terre, un castello, una ricchezza senza misura. Posso fare di te il più grande signore del Poitou. Se mi prendi per moglie. »\n\n« Sposereste un uomo con le mani macchiate di sangue? »\n\n« Sposerei un uomo dal cuore sincero che ha commesso un terribile errore. »",
                            ],
                            [
                                'name' => 'Il giuramento',
                                'contents' => "Raimondino la guardò e non vide né ironia né inganno. « Vi sposerò, » disse.\n\nMelusina sorrise. « Ma devi giurarmi una cosa. Ogni sabato, devo essere sola. Devo rinchiudermi nella mia camera, e nessuno — nemmeno tu — deve guardarmi. Giuralo, e ti darò tutto. »\n\n« Giuro sulla mia anima. »\n\n« Allora vieni, » disse ella, prendendogli la mano. « Lascia che ti mostri dove vivremo. »",
                            ],
                            [
                                'name' => 'Un castello elevato in una notte',
                                'contents' => "Melusina condusse Raimondino a un promontorio selvaggio che dominava il fiume — una roccia scoscesa coperta di rovi e spine.\n\n« Qui, disse ella, ti costruirò un castello. »\n\nQuella notte, Raimondino dormì sotto una quercia. Melusina camminò verso la roccia e alzò le braccia. Invocò potenze più antiche della croce — gli spiriti della terra, i sussurri nella pietra.\n\nTutta la notte il terreno tremò. Blocchi di marmo bianco si sollevarono dal suolo. Le torri si spinsero in alto come alberi. Le mura si tessevano insieme. All\'alba, il castello di Lusignano si ergeva completo — porte, bastioni, sala grande e cappella.\n\nRaimondino si svegliò e lo vide. Cadde in ginocchio.\n\n« È un miracolo, » mormorò.\n\n« È una casa, » disse Melusina.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'I figli di Lusignano',
                        'description' => 'Melusina sposa Raimondino, partorisce otto figli segnati e un nono nascosto, e vede quattro di loro conquistare corone e titoli attraverso l\'Europa e l\'Oriente.',
                        'scenes' => [
                            [
                                'name' => 'Il signore e la signora di Lusignano',
                                'contents' => "Il vescovo di Poitiers li sposò nella cappella del castello. La bellezza di Melusina era celebrata in ogni corte di Francia. La sua generosità era senza limiti — offriva ricchi doni a ogni ospite, e nessuno se ne andava affamato.\n\nRaimondino fu proclamato signore di Lusignano.\n\nMa ogni sabato, Melusina si ritirava nella camera della sua torre. Barricava la porta di ferro e non parlava con nessuno. I servi sussurravano. Le serve della cucina si facevano il segno della croce. Raimondino non diceva nulla.",
                            ],
                            [
                                'name' => 'Otto figli segnati',
                                'contents' => "Con il tempo, Melusina partorì a Raimondino otto figli. Ciascuno venne al mondo con un segno strano, segno del sangue fatato della loro madre.\n\nUrien, il primogenito, aveva un\'orecchia rossa, unica — l\'orecchia di un lupo. Divenne re a Cipro.\n\nGuyon, il secondo, aveva l\'occhio sinistro che luccicava come quello di un gatto nell\'oscurità. Avrebbe ucciso un gigante nel Lussemburgo.\n\nAntoine, il terzo, portava sulla guancia un segno di graffio dove l\'unghia di sua madre lo aveva sfiorato nella notte. Avrebbe conquistato metà dell\'Armenia.\n\nReynaud, il quarto, aveva un occhio posto più in alto dell\'altro. Sarebbe diventato connestabile di Francia.\n\nGeoffroy, il quinto — chiamato Geoffroy la Gran Dente — portava una zanna d\'osso che sporgeva dalla sua mascella. Il suo temperamento era una fornace. Avrebbe incendiato un\'abbazia e ucciso il suo stesso fratello.\n\nFromont, il sesto, portava un segno rosso attraverso il naso, come il cappuccio di un monaco. Era l\'unico dolce. Entrò negli ordini e pregò per i peccati della sua famiglia.\n\nThierry, il settimo, aveva un occhio rosso come il sangue. Sarebbe diventato signore di Vouvant.\n\nRaymondet, l\'ottavo e ultimo, aveva tre occhi — due al loro posto ordinario e uno sopra il naso. Sua madre lo amava più di tutti gli altri.",
                            ],
                            [
                                'name' => 'Il fanciullo delle cantine',
                                'contents' => "Ma c\'era un nono figlio, nato in segreto, che Melusina nascondeva nelle cantine più profonde di Lusignano. Si chiamava Orribile.\n\nNon portava alcun segno sul volto — tutto il suo corpo era il segno. La sua pelle era squamosa come quella di una lucertola. Le sue dita terminavano in artigli. I suoi denti erano aghi. Crebbe nell\'oscurità, nutrendosi di carne cruda, esprimendosi in sibili.\n\nMelusina lo visitava di notte. « Sei mio figlio, » sussurrava, « ma non puoi camminare tra gli uomini. Ti terrorizzebbero. »\n\nOrribile rosicchiava un osso e non diceva nulla.",
                            ],
                            [
                                'name' => 'Quattro navi lasciano il Poitou',
                                'contents' => "Lo stesso mese, quattro navi lasciarono il porto del Poitou.\n\nUrien salpò verso est, verso l\'isola di Cipro. I Saraceni tenevano la costa, ma Urien bruciò la loro flotta e ruppe le loro linee in una sola mattina. Il re di Cipro gli diede sua figlia, e Urien portò una corona.\n\nGuyon cavalcò verso nord, fino al Lussemburgo. Un gigante di nome Maldichet divorava i bambini dei villaggi. Guyon lo incontrò su un ponte di pietra sopra la Sûre. Combatterono per tre ore. Il gigante spezzò lo scudo di Guyon. Guyon tagliò i tendini del gigante, poi gli conficcò la spada sotto le costole. Il duca del Lussemburgo lo armò cavaliere sul campo di battaglia.\n\nAntoine salpò verso est, verso l\'Armenia. Il Sultano assediava la capitale da sette anni. Antoine cavalcò solo davanti alle mura e sfidò il campione del Sultano. Un solo colpo — dalla sella al mento. Il re dell\'Armenia gli offrì metà del suo regno e la mano di sua figlia.\n\nReynaud cavalcò verso Parigi, alla corte del re di Francia. Si unì all\'esercito reale, combatté in tre campagne, e non perse mai una scaramuccia. Il re lo fece connestabile di Francia, il più alto cavaliere del regno.",
                            ],
                            [
                                'name' => 'Melusina al telaio',
                                'contents' => "Nel frattempo, Melusina, seduta nella sua torre di Lusignano, filava un filo d\'argento. Vedeva ciascuno dei suoi figli nel filo — Urien sul suo trono, Guyon accanto al corpo del gigante, Antoine che si pulisce la spada, Reynaud inginocchiato davanti al re.\n\n« Quattro sono salvi, » disse. « Ma il quinto — »\n\nVide il fuoco.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'Il veleno e il buco della serratura',
                        'description' => 'La rabbia di Geoffroy incendia un\'abbazia e uccide suo fratello, Orribile scappa dalle cantine, e il dubbio seminato dal conte di Forez spinge Raimondino a spiare sua moglie.',
                        'scenes' => [
                            [
                                'name' => 'Il fuoco di Geoffroy',
                                'contents' => "Geoffroy era rimasto nel Poitou. Il suo temperamento cresceva con la sua forza. Quando l\'abate di Malliers gli rifiutò il passaggio attraverso le terre di caccia dell\'abbazia, il sangue di Geoffroy si infiammò.\n\n« Rifiutarmi? » ruggì. « Sono un figlio di Lusignano. Nessun monaco mi barrerà il cammino. »\n\nQuella notte tornò con fiaccole. Diede fuoco al tetto dell\'abbazia. Le fiamme si propagarono dalla cappella al dormitorio alla biblioteca. I monaci correvano urlando nella notte, le loro vesti in fiamme.\n\nDentro l\'abbazia, venuto a pregare, si trovava Fromont — il proprio fratello di Geoffroy, il dolce sesto figlio.\n\nFromont morì nelle fiamme, il suo cappuccio di monaco che bruciava sul suo volto.",
                            ],
                            [
                                'name' => 'La gabbia spezzata',
                                'contents' => "Nelle cantine di Lusignano, Orribile sentì il fuoco. Lo sentì attraverso la pietra. Udì le grida nel suo sangue.\n\nSpezzò le sbarre di ferro della sua gabbia e si trascinò su nel castello.\n\nQuella notte, tre servi scomparvero. Al mattino, rimangono solo ossa.\n\nMelusina trovò Orribile nelle cucine, accovacciato su un quarto corpo. Non lo rimproverò.\n\n« Non puoi rimanere qui, » disse dolcemente. « Distruggerai tutto. »\n\nLo condusse verso un tunnel segreto sotto il castello, un passaggio che si apriva sui boschi profundi. « Va verso nord, » disse. « Trova le montagne. Vivi nelle grotte. Ti invierò del cibo. »\n\nOrribile la guardò con i suoi occhi gialli. « Madre, » sibilò. Quella fu l\'unica parola che pronunciò mai.\n\nSparì nell\'oscurità.",
                            ],
                            [
                                'name' => 'Due messaggeri',
                                'contents' => "Due messaggeri arrivarono a Lusignano lo stesso giorno.\n\nIl primo portava notizie di Malliers. « Geoffroy ha incendiato l\'abbazia di Malliers. Tutti i monaci sono morti. E Fromont — tuo figlio Fromont — era tra loro. »\n\nIl volto di Raimondino divenne grigio. « Mio figlio ha ucciso suo fratello? »\n\n« Il fuoco l\'ha preso, mio signore. Per mano di Geoffroy o dalle fiamme, nessuno lo sa. »\n\nIl secondo messaggero era il conte di Forez, un nobile geloso che da tempo invidiava l\'ascesa di Raimondino.\n\n« Notizia tragica, mio signore, » disse Forez, versando vino. « Ma mi chiedo — non avete mai dubitato dei strani abitudini di vostra moglie? Questi sabati segreti? Questa ricchezza improvvisa? Questi figli nati con artigli e zanne? »\n\n« Tenete la lingua, » disse Raimondino.\n\n« Penso solo a voi, amico mio. Che tipo di donna partorisce mostri? »",
                            ],
                            [
                                'name' => 'Ciò che Raimondino vide',
                                'contents' => "Quel sabato, Raimondino non riusciva a trovare riposo. Camminava per i corridoi del suo castello come un fantasma. Le parole di Forez risuonavano nel suo cranio.\n\n*Che tipo di donna partorisce mostri?*\n\nSi trovò davanti alla camera di Melusina. Un filo di luce di candela filtrava dal buco della serratura.\n\nSi inginocchiò.\n\nGuardò.\n\nDentro, Melusina era seduta in un grande bagno di marmo pieno d\'acqua fumante. Dalla cintola in su era la donna che aveva sposato — le sue spalle bianche, i suoi capelli scuri, il suo volto calmo come l\'avorio scolpito. Ma dalla cintola ai piedi, il suo corpo era quello di un serpente — una lunga e spessa coda coperta di scaglie d\'argento e blu, che si arrotolava e si distendeva nell\'acqua come una cosa viva.\n\nIl cuore di Raimondino si fermò. Cadde indietro, una mano sulla bocca.\n\nSi allontanò senza un suono.",
                            ],
                            [
                                'name' => 'Una settimana di silenzio',
                                'contents' => "Per una settimana, Raimondino non disse nulla. Si sedeva a cena di fronte a Melusina e non riusciva a sostenere il suo sguardo. Le toccava la mano e sentiva un brivido di paura.\n\nMelusina lo osservava. Sapeva.",
                            ],
                        ],
                    ],
                    [
                        'name' => 'La rottura',
                        'description' => 'La notizia della morte di Urien arriva, Raimondino denuncia Melusina davanti a tutta la corte, e lei si trasforma in un serpente alato e si alza in volo da Lusignano per sempre.',
                        'scenes' => [
                            [
                                'name' => 'La morte di Urien',
                                'contents' => "Il terzo messaggero arrivò sette giorni dopo. Urien era morto a Cipro. I Saraceni lo avevano colto in imboscata in un passo montano. Era morto con la spada in mano, coperto di ferite, ma non era caduto fino a quando non aveva messo i suoi uomini al sicuro.\n\nRaimondino ricevette la notizia nella grande sala, circondato dai suoi cavalieri.",
                            ],
                            [
                                'name' => 'La denuncia',
                                'contents' => "Si alzò. Il suo volto era pallido come l\'osso.\n\n« Ho custodito un segreto troppo a lungo, » disse. « Ho protetto un demone nella mia stessa casa. »\n\n« Raimondino — » disse Melusina, entrando nella sala. « Non dire nulla. »\n\n« Mia moglie è un serpente! » gridò Raimondino, indicandola col dito. « Ogni sabato, diventa serpente dalla cintola ai piedi. L\'ho vista con i miei stessi occhi. I nostri figli — questi mostri — sono la prole di una bestia fatata! »\n\nLa sala esplose. I cavalieri si facevano il segno della croce. Le dame urlavano.",
                            ],
                            [
                                'name' => 'Un fiore che si apre',
                                'contents' => "Melusina stava al centro della sala. Non pianse. Non si arrabbiò. Il suo volto portava solo un dolore antico e senza fondo.\n\n« Hai rotto il tuo giuramento, » disse. « Hai guardato. E ora hai parlato. »\n\n« Tuo figlio ha ucciso suo fratello! » gridò Raimondino. « Tuo altro figlio lacera gli uomini nell\'oscurità! Sei maledetta, donna, e mi hai maledetto con te! »\n\n« Non ti ho mai maledetto. Ti ho amato. Ti ho dato tutto. »\n\nAlzò le braccia. La sua forma cominciò a cambiare — non nel dolore, ma come un fiore che si apre. Il suo vestito si dissolse in nebbia. Ali d\'argento e bianche si dispiegarono dalle sue spalle, abbastanza larghe da toccare le pareti. Le sue gambe si fusero, si allungarono, divennero la coda di un serpente. Scaglie di madreperla e zaffiro salirono dalla sua cintola, dalle sue costole, dalla sua gola.\n\nSi alzò nell\'aria — una donna-drago, bella e terribile.\n\n« Ti ho amato, » disse. « Ho amato i nostri figli. Non ho amato nessun altro in tutti i lunghi anni della mia vita. »\n\nFece un giro della grande sala, poi volò dalla finestra nella notte.",
                            ],
                            [
                                'name' => 'Intorno alle torri',
                                'contents' => "Melusina volò tre volte intorno al castello di Lusignano. Il suo grido non era un urlo — era un suono simile a un\'arpa che si spezza, a un ghiaccio che si crepa su un fiume gelato. Lo sentirono in ogni villaggio a una giornata di cammino.\n\nAl primo giro, le torri piansero malte.\n\nAl secondo giro, le porte gemettero e si spacchettarono.\n\nAl terzo giro, si alzò tra le nuvole e scomparve.",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Dopo Melusina',
                'description' => "<p>Dopo il volo di Melusina, Raimondino muore penitente, Orribile cresce selvaggio nelle montagne, e i figli di Lusignano conoscono il loro destino uno per uno fino a quando la stirpe e il castello sprofondano nella rovina e nella leggenda.</p>",
                'chapters' => [
                    [
                        'name' => 'La rovina di Lusignano',
                        'description' => 'L\'apparizione di Melusina, la morte penitente di Raimondino, l\'esilio di Orribile, la caduta dei figli uno per uno, e la lenta rovina del castello nella leggenda.',
                        'scenes' => [
                            [
                                'name' => 'Legata dal ricordo',
                                'contents' => "Melusina non partì del tutto.\n\nLa notte prima che un signore di Lusignano muoia, appare sulla torre più alta, vestita di bianco, i capelli sciolti al vento. Piange, e i suoi pianti risuonano come l\'acqua che scorre sulla pietra.\n\nRimane legata al castello — legata dall\'amore, dal ricordo, dai figli che vi ha messo al mondo.",
                            ],
                            [
                                'name' => 'Il penitente',
                                'contents' => "Raimondino lasciò Lusignano quello stesso anno. Errò sulle strade del Poitou, vestito di un mantello consumato, penitente senza riposo. Visitò ogni chiesa, ogni santuario, ogni grotta di eremita.\n\nMorì sette anni dopo in una capanna di legno, solo tranne per un prete.\n\nLa sua ultima parola fu il suo nome.",
                            ],
                            [
                                'name' => 'Le Ardenne',
                                'contents' => "Lontano al nord, nelle grotte delle Ardenne, Orribile crebbe, immenso e selvaggio. Mangiava cervi crudi e beveva ai ruscelli di montagna. A volte, di notte, guardava verso sud, verso Lusignano, e fischiava.\n\nNon rivide mai sua madre.",
                            ],
                            [
                                'name' => 'Uno per uno',
                                'contents' => "Le morti vennero una dopo l\'altra.\n\nGuyon cadde nel Lussemburgo, difendendo il duca che lo aveva onorato. Una freccia di balestra in gola.\n\nAntoine morì in Armenia, il suo regno che bruciava intorno a lui quando il Sultano tornò con un esercito più grande.\n\nReynaud fu ucciso durante una campagna francese contro gli Inglesi. Una freccia nell\'occhio — quello più in alto.\n\nGeoffroy non fu mai visto dopo la morte di suo padre. Alcuni dicono che salpò verso la Terra Santa. Altri dicono che seguì sua madre nell\'altro mondo. Altri ancora dicono che ancora cammina per le strade del Poitou, gigante con una zanna, cercando una lotta che non può vincere.\n\nThierry visse più a lungo. Tenne Vouvant per quarant\'anni e morì nel suo letto, circondato dai suoi figli. Il suo occhio rosso si chiuse in pace.\n\nRaymondet — quello dai tre occhi, il ben amato — entrò in un monastero e passò la sua vita a copiare libri. Nessuno sa quando morì.",
                            ],
                            [
                                'name' => 'Racconti dalle caverne',
                                'contents' => "E Orribile? Nelle grotte del nord, i cacciatori raccontano la storia di una bestia con scaglie e occhi gialli. Si dice che abbia la forma di un uomo ma la fame di un serpente. Si dice che non attacchi mai senza essere provocata.\n\nNon sanno che è l\'ultimo figlio di Melusina.",
                            ],
                            [
                                'name' => 'Un cumulo di pietre rotte',
                                'contents' => "Il castello passò di mano in mano. La stirpe di Lusignano si fuse in altre case — la casa di Cipro, il regno di Gerusalemme, le corti di Francia e Inghilterra. Il sangue di Melusina scorreva nei re, ma nessun re ricordava il suo nome.\n\nIl castello crollò. Le torri caddero. La grande sala dove Melusina aveva dispiegato le sue ali divenne un cumulo di pietre rotte.",
                            ],
                            [
                                'name' => 'Fino al ritorno del prossimo signore',
                                'contents' => "Ancora oggi, quando il vento soffia sulle rovine di Lusignano, una donna si sente piangere.\n\nE in alcune notti — quando la luna è piena e le nuvole corrono basse — un serpente alato sorvola le torri rotte. Una volta. Due volte. Tre volte.\n\nPoi scompare.\n\nFino al ritorno del prossimo signore.",
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
            ['name' => 'Colore dei capelli'],
            ['applies_to' => [CodexEntryType::Character], 'position' => 1],
        );

        $frescoes = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Affreschi'],
            ['applies_to' => [CodexEntryType::Location], 'position' => 2],
        );

        $fortunes = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Fortuna'],
            ['applies_to' => [CodexEntryType::Organization], 'position' => 3],
        );

        $reputation = $project->codexAttributes()->firstOrCreate(
            ['name' => 'Reputazione'],
            ['applies_to' => [CodexEntryType::Character, CodexEntryType::Organization], 'position' => 4],
        );

        // --- Characters ---

        $melusine = $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Melusina',
            '<p>Una fata del bosco profondo, <strong>maledetta</strong> a prendere forma di serpente dalla cintola ai piedi ogni sabato. Moglie di Raimondino e madre dei nove figli di Lusignano.</p>',
            ['Melusina', 'La Dama Serpente', 'Signora di Lusignano'],
            ['Fata', 'Protagonista', 'Maledetta'],
        );

        // Melusina's hair over time: raven black by default, curse-touched after Pressine's
        // judgment, and loose and wild once she takes her winged serpent form.
        $this->seedPeriods($melusine, $hairColor, [
            [null, 'Nero corvino, che scende fino alla cintola'],
            ['La seconda maledizione', 'Nero corvino, rigato d\'argento il sabato'],
            ['La trasformazione', 'Selvaggi e sciolti attorno alle sue ali'],
        ], $eventsByTitle);

        $this->seedPeriods($melusine, $reputation, [
            [null, 'Una fata sconosciuta della fontana del bosco'],
            ['La costruzione di Lusignano', 'La signora amata e generosa di Lusignano'],
            ['La trasformazione', 'Denunciata davanti alla corte come un demone-serpente'],
        ], $eventsByTitle);

        $raymondin = $this->seedEntry(
            $project,
            CodexEntryType::Character,
            'Raimondino di Lusignano',
            "<p>Un giovane cavaliere del Poitou che uccide accidentalmente suo zio, sposa Melusina, e diventa il primo <em>signore di Lusignano</em> — fino a quando il suo giuramento tradito li perde entrambi.</p>",
            ['Raimondo', 'Signore di Lusignano'],
            ['Cavaliere', 'Protagonista'],
        );

        $this->seedPeriods($raymondin, $hairColor, [
            [null, 'Castano e bruno'],
            ['La trasformazione', 'Ingrigito dal dolore'],
        ], $eventsByTitle);

        $this->seedPeriods($raymondin, $reputation, [
            [null, 'Un nipote di scarsa importanza del conte di Poitiers'],
            ['La costruzione di Lusignano', 'Il signore emergente di Lusignano'],
            ['La trasformazione', 'Un vedovo rotto e penitente'],
        ], $eventsByTitle);

        // --- Location ---

        $castle = $this->seedEntry(
            $project,
            CodexEntryType::Location,
            'Il castello di Lusignano',
            '<p>Il grande castello di marmo bianco che Melusina elevò in una <strong>sola notte</strong> su un promontorio spinoso sopra il fiume.</p>',
            ['Lusignano'],
            ['Castello', 'Poitou'],
        );

        // The painted walls of the great hall, from bare rock to fresh marble to slow ruin.
        $this->seedPeriods($castle, $frescoes, [
            [null, 'Nessuno — un promontorio di roccia nuda e spinosa'],
            ['La costruzione di Lusignano', 'Muri di marmo bianco, appena elevati e senza ornamento'],
            ['La trasformazione', 'Muri screpolati che piangono malta dove Melusina ha girato'],
        ], $eventsByTitle);

        // --- Organization ---

        $house = $this->seedEntry(
            $project,
            CodexEntryType::Organization,
            'La Casa di Lusignano',
            "<p>La stirpe nobile fondata da Melusina e Raimondino, i cui figli conquistano corone attraverso l\'Europa e l\'Oriente prima che la casa si fonda in altre dinastie.</p>",
            ['I Lusignani'],
            ['Casa nobile'],
        );

        $this->seedPeriods($house, $fortunes, [
            [null, 'Non ancora fondata'],
            ['La costruzione di Lusignano', 'Signori appena stabiliti di un castello elevato dalla magia'],
            ['Le grandi conquiste', 'Corone e titoli conquistati attraverso l\'Europa e l\'Oriente'],
            ['La caduta di Lusignano', 'Fondata in altre case; il castello caduto in rovina'],
        ], $eventsByTitle);

        $this->seedPeriods($house, $reputation, [
            [null, 'Un nome sconosciuto'],
            ['Le grandi conquiste', 'Rinomata in tutta la cristianità'],
        ], $eventsByTitle);
    }

    /**
     * Create (idempotently) one Codex entry with its aliases and tags.
     *
     * Aliases are firstOrCreate'd children; tags are firstOrCreate'd once per project name
     * and attached without detaching, so entries can share tags (e.g. "Protagonista").
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

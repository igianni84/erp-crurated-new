<?php

namespace Database\Seeders;

use App\Enums\DataSource;
use App\Models\Pim\ProductMedia;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * ProductMediaSeeder - Seeds wine bottle images for all wine variants.
 *
 * Copies images from database/seeders/images/wines/ into storage and creates
 * ProductMedia records with is_primary=true for each variant.
 * Also updates WineVariant.thumbnail_url for quick display.
 */
class ProductMediaSeeder extends Seeder
{
    /**
     * Mapping from WineMaster (name + producer) to image filename.
     *
     * @var array<string, string>
     */
    private array $imageMap = [
        // Piedmont - Barolo
        'Barolo Cannubi|Giacomo Conterno' => 'giacomo-conterno-barolo-cannubi.png',
        'Barolo Monfortino|Giacomo Conterno' => 'giacomo-conterno-barolo-monfortino.png',
        'Barolo Falletto|Bruno Giacosa' => 'bruno-giacosa-barolo-falletto.png',
        'Barolo Brunate|Roberto Voerzio' => 'roberto-voerzio-barolo-brunate.png',
        "Barolo Rocche dell'Annunziata|Paolo Scavino" => 'paolo-scavino-barolo-rocche-annunziata.png',
        'Barolo Bussia|Aldo Conterno' => 'aldo-conterno-barolo-bussia.png',
        // Piedmont - Barbaresco
        'Barbaresco Asili|Bruno Giacosa' => 'bruno-giacosa-barbaresco-asili.png',
        'Barbaresco Sori Tildin|Gaja' => 'gaja-barbaresco-sori-tildin.png',
        'Barbaresco Sori San Lorenzo|Gaja' => 'gaja-barbaresco-sori-san-lorenzo.png',
        'Barbaresco Rabaja|Bruno Rocca' => 'bruno-rocca-barbaresco-rabaja.png',
        // Tuscany - Brunello
        'Brunello di Montalcino|Biondi-Santi' => 'biondi-santi-brunello-di-montalcino.png',
        'Brunello di Montalcino Riserva|Biondi-Santi' => 'biondi-santi-brunello-di-montalcino-riserva.png',
        'Brunello di Montalcino Poggio alle Mura|Castello Banfi' => 'castello-banfi-brunello-poggio-alle-mura.png',
        'Brunello di Montalcino Cerretalto|Casanova di Neri' => 'casanova-di-neri-brunello-cerretalto.png',
        'Brunello di Montalcino Madonna delle Grazie|Il Marroneto' => 'il-marroneto-brunello-madonna-delle-grazie.png',
        // Tuscany - Super Tuscans
        'Sassicaia|Tenuta San Guido' => 'tenuta-san-guido-sassicaia.png',
        "Ornellaia|Tenuta dell'Ornellaia" => 'tenuta-ornellaia-ornellaia.png',
        "Masseto|Tenuta dell'Ornellaia" => 'tenuta-ornellaia-masseto.png',
        'Tignanello|Marchesi Antinori' => 'antinori-tignanello.png',
        'Solaia|Marchesi Antinori' => 'antinori-solaia.png',
        'Guado al Tasso|Marchesi Antinori' => 'antinori-guado-al-tasso.png',
        'Flaccianello della Pieve|Fontodi' => 'fontodi-flaccianello.png',
        // Tuscany - Chianti
        'Chianti Classico Gran Selezione|Castello di Ama' => 'castello-di-ama-chianti-classico-gran-selezione.png',
        'Chianti Classico Riserva|Felsina' => 'felsina-chianti-classico-riserva.png',
        // Veneto - Amarone
        'Amarone della Valpolicella Classico|Giuseppe Quintarelli' => 'quintarelli-amarone.png',
        'Amarone della Valpolicella Classico|Bertani' => 'bertani-amarone.png',
        'Amarone della Valpolicella|Allegrini' => 'allegrini-amarone.png',
        'Amarone della Valpolicella TB|Dal Forno Romano' => 'dal-forno-romano-amarone.png',
        // Bordeaux - Left Bank
        'Chateau Margaux|Chateau Margaux' => 'chateau-margaux.png',
        'Chateau Latour|Chateau Latour' => 'chateau-latour.png',
        'Chateau Lafite Rothschild|Chateau Lafite Rothschild' => 'chateau-lafite-rothschild.png',
        'Chateau Mouton Rothschild|Chateau Mouton Rothschild' => 'chateau-mouton-rothschild.png',
        'Chateau Haut-Brion|Chateau Haut-Brion' => 'chateau-haut-brion.png',
        'Chateau Leoville Las Cases|Chateau Leoville Las Cases' => 'chateau-leoville-las-cases.png',
        "Chateau Cos d'Estournel|Chateau Cos d'Estournel" => 'chateau-cos-destournel.png',
        // Bordeaux - Right Bank
        'Petrus|Petrus' => 'petrus.png',
        'Le Pin|Le Pin' => 'le-pin.png',
        'Chateau Cheval Blanc|Chateau Cheval Blanc' => 'chateau-cheval-blanc.png',
        'Chateau Ausone|Chateau Ausone' => 'chateau-ausone.png',
        // Burgundy - Red
        'Romanee-Conti Grand Cru|Domaine de la Romanee-Conti' => 'drc-romanee-conti.png',
        'La Tache Grand Cru|Domaine de la Romanee-Conti' => 'drc-la-tache.png',
        'Richebourg Grand Cru|Domaine de la Romanee-Conti' => 'drc-richebourg.png',
        'Musigny Grand Cru|Domaine Georges de Vogue' => 'vogue-musigny.png',
        'Chambertin Grand Cru|Domaine Armand Rousseau' => 'armand-rousseau-chambertin.png',
        'Clos de la Roche Grand Cru|Domaine Dujac' => 'dujac-clos-de-la-roche.png',
        // Burgundy - White
        'Montrachet Grand Cru|Domaine de la Romanee-Conti' => 'drc-montrachet.png',
        'Corton-Charlemagne Grand Cru|Domaine Coche-Dury' => 'coche-dury-corton-charlemagne.png',
        // Champagne
        'Dom Perignon|Moet & Chandon' => 'dom-perignon.png',
        'Cristal|Louis Roederer' => 'louis-roederer-cristal.png',
        'Krug Grande Cuvee|Krug' => 'krug-grande-cuvee.png',
        'Salon Le Mesnil|Salon' => 'salon-le-mesnil.png',
        // Rhone
        'Chateauneuf-du-Pape Hommage a Jacques Perrin|Chateau de Beaucastel' => 'beaucastel-hommage-jacques-perrin.png',
        'Hermitage La Chapelle|Paul Jaboulet Aine' => 'jaboulet-hermitage-la-chapelle.png',
        'Cote Rotie La Landonne|E. Guigal' => 'guigal-cote-rotie-la-landonne.png',
    ];

    public function run(): void
    {
        $sourceDir = database_path('seeders/images/wines');
        $targetDir = 'pim/product-media/images';

        // Ensure the storage directory exists
        Storage::disk('public')->makeDirectory($targetDir);

        // Ensure the storage symlink exists
        if (! File::exists(public_path('storage'))) {
            $this->command->call('storage:link');
        }

        $wineMasters = WineMaster::all()->keyBy(function (WineMaster $master) {
            return $master->name.'|'.$master->producer;
        });

        $mediaCreated = 0;
        $thumbnailsSet = 0;

        foreach ($this->imageMap as $key => $filename) {
            $sourcePath = $sourceDir.'/'.$filename;

            if (! File::exists($sourcePath)) {
                $this->command->warn("Image not found: {$filename}");

                continue;
            }

            $master = $wineMasters->get($key);
            if ($master === null) {
                $this->command->warn("WineMaster not found: {$key}");

                continue;
            }

            // Get all variants for this master
            $variants = WineVariant::where('wine_master_id', $master->id)->get();

            if ($variants->isEmpty()) {
                continue;
            }

            foreach ($variants as $variant) {
                // Copy file to storage with a unique path per variant
                $storagePath = $targetDir.'/'.$variant->id.'/'.$filename;
                Storage::disk('public')->put(
                    $storagePath,
                    File::get($sourcePath)
                );

                // Create ProductMedia record
                ProductMedia::firstOrCreate(
                    [
                        'wine_variant_id' => $variant->id,
                        'is_primary' => true,
                    ],
                    [
                        'type' => 'image',
                        'source' => DataSource::Manual,
                        'file_path' => $storagePath,
                        'original_filename' => $filename,
                        'mime_type' => 'image/png',
                        'file_size' => File::size($sourcePath),
                        'alt_text' => $master->name.' '.$master->producer.' bottle',
                        'caption' => $master->name.' by '.$master->producer,
                        'sort_order' => 1,
                        'is_locked' => false,
                    ]
                );
                $mediaCreated++;

                // Store disk-relative path (Filament resolves via Storage::disk('public')->url())
                $variant->update(['thumbnail_url' => $storagePath]);
                $thumbnailsSet++;
            }
        }

        $this->command->info("Created {$mediaCreated} product media records.");
        $this->command->info("Set {$thumbnailsSet} variant thumbnails.");
    }
}

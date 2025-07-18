<?php

namespace Bespredel\GeoRestrict\Console;

use Bespredel\GeoRestrict\Services\GeoCache;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class ClearGeoCache extends Command
{
    /**
     * Name the command.
     *
     * @var string
     */
    protected $signature = 'geo-restrict:clear-cache';

    /**
     * Description the command.
     *
     * @var string
     */
    protected $description = 'Flush all GeoIP cache (by tag if supported by cache driver).';

    /**
     * @var GeoCache
     */
    protected GeoCache $geoCache;

    /**
     * Constructor.
     *
     * @param GeoCache $geoCache
     */
    public function __construct(GeoCache $geoCache)
    {
        parent::__construct();
        $this->geoCache = $geoCache;
    }

    /**
     * Command execution.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->geoCache->clearAllGeoCache();

        if (method_exists($this, 'components')) {
            $this->components->info('GeoIP cache flushed (if supported by cache driver).');
        } else {
            $this->info('GeoIP cache flushed (if supported by cache driver).');
        }

        return SymfonyCommand::SUCCESS;
    }
}
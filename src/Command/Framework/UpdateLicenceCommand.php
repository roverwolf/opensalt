<?php

namespace App\Command\Framework;

use App\Command\BaseCommand;
use App\Entity\Framework\LsDefLicence;

class UpdateLicenceCommand extends BaseCommand
{
    /**
     * @var LsDefLicence
     */
    private $licence;

    public function __construct(LsDefLicence $licence)
    {
        $this->licence = $licence;
    }

    public function getLicence(): LsDefLicence
    {
        return $this->licence;
    }
}

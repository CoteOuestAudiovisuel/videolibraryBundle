<?php

namespace Coa\VideolibraryBundle\Extensions\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class WithoutQueryParams extends AbstractExtension{
    public function getFilters(){
        return [
            new TwigFilter('without_qs', [$this, 'qs']),
        ];
    }

    public function qs($value){
        $value = explode("?",$value);
        return array_shift($value);
    }
}

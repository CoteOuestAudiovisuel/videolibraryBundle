<?php

namespace Coa\VideolibraryBundle\Extensions\Twig;

use Coa\VideolibraryBundle\Entity\Video;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AwsS3Url extends AbstractExtension
{
    private ContainerBagInterface $container;

    public function __construct(ContainerBagInterface $container)
    {
        $this->container = $container;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('coaBucketBasename', [$this, 'urlBasename']),
        ];
    }

    public  function urlBasename(string $key, Video $video): string
    {
        $bucket = $video->getBucket();
        $region = $video->getRegion();
        $provider = $video->getProvider();

        switch ($provider) {
            case 'GCP':
                $base_url = $this->container->get('coa_videolibrary.gcp_cdn_default');
                break;
            case 'AWS':
            default:
                $base_url = $this->container->get('coa_videolibrary.aws_cdn_default');
                break;
        }


        return $base_url . "/aws/$key";
    }
}
<?php

namespace Example\Agents;

use Example\Service\GooglePriceGenerator;

/**
 * Class GenerateGooglePrice
 *
 * @package Example\Agents
 */

class GenerateGooglePrice
{
    /**
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function run(): string
    {
        $generator = new GooglePriceGenerator();
        $generator->run();
        return 'Example\Agents\GenerateGooglePrice::run();';
    }
}

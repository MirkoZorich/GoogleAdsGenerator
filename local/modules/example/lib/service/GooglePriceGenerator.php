<?php

namespace Example\Service;

use Bitrix\Main;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Diag\Debug;
use Vendor\SiteCore\Core;

class GooglePriceGenerator
{
    /**
     * @var int
     */
    protected int $IblockId;
    /**
     * @var int
     */
    protected int $offersIbId;
    /**
     * @var int
     */
    protected int $priceLimit;
    /**
     * @var string
     */
    protected string $siteName;
    /**
     * @var array
     */
    protected array $RO;

    /**
     * @var string
     */
    protected string $filePrefix;

    /**
     * @var string
     */
    protected string $fileExtension;

    /**
     * @var string
     */
    protected string $fileDir;

    /**
     * @var string
     */
    protected string $debugFilePath;

    /**
     * @var string
     */
    protected string $rootUrl;

    /**
     * GooglePriceGenerator constructor.
     *
     * @throws ArgumentException
     * @throws Main\LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function __construct()
    {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $this->IblockId = Core::getInstance()->getIblockId(Core::IB_CODE_CATALOG);
        $this->offersIbId = Core::getInstance()->getIblockId(Core::IB_CODE_OFFERS);
        $this->priceLimit = 30000;
        $this->siteName = SITE_SERVER_NAME;
        $this->rootUrl = '/catalog/';
        $this->fileDir = $_SERVER['DOCUMENT_ROOT'] . '/google_price/';
        $this->filePrefix = 'google_';
        $this->fileExtension = '.csv';
        $this->RO = array(
            array('ID' => 458, 'PRICE_ZONE' => 'ZONE_1', 'PRICE_ID' => 1, 'CODE' => 'MO'),
            array('ID' => 565, 'PRICE_ZONE' => 'ZONE_2', 'PRICE_ID' => 1, 'CODE' => 'MV'),
            array('ID' => 125, 'PRICE_ZONE' => 'ZONE_2', 'PRICE_ID' => 2, 'CODE' => 'MC'),
        );
        $this->debugFilePath = 'local/logs/GooglePriceGenerator_'.date("Y-m-d").'.log';
    }

    public function run(): void
    {
        if (!is_dir($this->fileDir)){
            mkdir($this->fileDir);
        }

        foreach ($this->RO as $ro) {

            $path = $this->fileDir . $this->filePrefix . $ro['CODE'] . $this->fileExtension;

            $this->writeToFile($path, "Page URL,Custom label\r\n", 'w');

            $products = $this->getProducts($ro);

            foreach($products as $product) {
                $entry = $this->makeProductEntry($product, $ro);
                ($entry) ? $this->writeToFile($path, $entry) : '';
            }

            unset($products);

            $allSku = $this->getSku($ro);

            foreach($allSku as $sku) {
                $entry = $this->makeSkuEntry($sku, $ro);
                ($entry) ? $this->writeToFile($path, $entry) : '';
            }

            unset($allSku);
        }
    }

    /**
     * @param array $RO
     * @return array
     */

    protected function getProducts(array $RO): array
    {
        $select = array(
            'ID',
            'NAME',
            'CODE',
            'IBLOCK_SECTION_ID',
            'IBLOCK_SECTION_CODE' => 'IBLOCK_SECTION.CODE',
            'ZONE_DISCOUNT_VALUE' => $RO['PRICE_ZONE'] . '_DISCOUNT.VALUE',
        );

        $filter = array(
            '=AVAILABLE_IN_RO.VALUE' => $RO['ID'],
            '!=ORDER.ITEM.VALUE' => 'Y',
            '>' . $RO['PRICE_ZONE'] . '_DISCOUNT.VALUE' => $this->priceLimit,
            '=ACTIVE' => 'Y',
        );

        $iblock = \Bitrix\Iblock\Iblock::wakeUp($this->IblockId);

        return $iblock->getEntityDataClass()::getList([
            'select' => $select,
            'filter' => $filter,
        ])->fetchAll();
    }

    /**
     * @param array $RO
     * @return array
     */
    protected function getSku(array $RO): array
    {
        $select = [
            'ID',
            'NAME',
            'CODE',
            'IBLOCK_SECTION_ID',
            'IBLOCK_SECTION_CODE' => 'IBLOCK_SECTION.CODE',
            'PRICE_ZONE' => 'PRICE.CATALOG_GROUP_ID',
            'PRICE_VALUE' => 'PRICE.PRICE',
            'PARENT_ELEMENT_ID' => 'CML2_LINK.ELEMENT.ID',
            'PARENT_ELEMENT_CODE' => 'CML2_LINK.ELEMENT.CODE',
            'PARENT_SECTION_ID' => 'CML2_LINK.ELEMENT.IBLOCK_SECTION_ID',
        ];

        $filter = [
            '=AVAILABLE_IN_RO.VALUE' => $RO['ID'],
            '=ACTIVE' => 'Y',
            '!=ORDER.ITEM.VALUE' => 'Y',
            '>PRICE.PRICE' => $this->priceLimit,
            '=PRICE.CATALOG_GROUP_ID' => $RO['PRICE_ID'],
            '=CML2_LINK.ELEMENT.ACTIVE' => 'Y'
        ];

        $iblock = \Bitrix\Iblock\Iblock::wakeUp($this->offersIbId);

        return $iblock->getEntityDataClass()::getList([
            'select' => $select,
            'filter' => $filter,
            'runtime' => [
                'PRICE' => [
                    'data_type' => \Bitrix\Catalog\PriceTable::class,
                    'reference' => [
                        '=this.ID' => 'ref.PRODUCT_ID',
                    ]
                ],
            ],
        ])->fetchAll();
    }

    protected function makeProductEntry($product, $ro)
    {
        $arrSections = \CIBlockSection::GetNavChain($this->IblockId, $product['IBLOCK_SECTION_ID'], array('NAME', 'CODE'), true);
        $url = $this->rootUrl;
        $breadCrumbs = '';
        foreach($arrSections as $section) {
            $url .= $section['CODE'] . '/';
            $breadCrumbs .= $section['NAME'] . ';';
        }

        if(strlen($url) <= strlen($this->rootUrl) || !$product['CODE']) {
            $mess = $ro['CODE'] . '_' . $product['ID'] . ' товар - нет url или code;';
            Debug::writeToFile($mess, "", $this->debugFilePath);
            return false;
        } else {
            $url .= $product['CODE'] . '/';
        }

        if($breadCrumbs == '') {
            $mess = $ro['CODE'] . '_' . $product['ID'] . ' товар - нет списка разделов;';
            Debug::writeToFile($mess, "", $this->debugFilePath);
            return false;
        } else {
            $breadCrumbs .= $product['NAME'] . ';';
        }

        $price = (int)$product['ZONE_DISCOUNT_VALUE'];

        $id = $product['ID'];

        return $this->siteName . $url . ',' . $breadCrumbs . $price . ' руб;' . 'ID ' . $id;
    }

    protected function makeSkuEntry($sku, $ro)
    {
        $arrSections = \CIBlockSection::GetNavChain($this->IblockId, $sku['PARENT_SECTION_ID'], array('NAME', 'CODE'), true);
        $url = $this->rootUrl;
        $breadCrumbs = '';
        foreach($arrSections as $section) {
            $url .= $section['CODE'] . '/';
            $breadCrumbs .= $section['NAME'] . ';';
        }

        if(strlen($url) <= strlen($this->rootUrl) || !$sku['CODE']) {
            $mess = $ro['CODE'] . '_' . $sku['ID'] . ' sku - нет url или code;';
            Debug::writeToFile($mess, "", $this->debugFilePath);
            return false;
        } else {
            $url .= $sku['CODE'] . '/';
        }

        if($breadCrumbs == '') {
            $mess = $ro['CODE'] . '_' . $sku['ID'] . ' sku - нет списка разделов;';
            Debug::writeToFile($mess, "", $this->debugFilePath);
            return false;
        } else {
            $breadCrumbs .= $sku['NAME'] . ';';
        }

        $price = (int)$sku['PRICE_VALUE'];

        $id = $sku['ID'];

        return $this->siteName . $url . ',' . $breadCrumbs . $price . ' руб;' . 'ID ' . $id;
    }

    /**
     * @param $RO
     * @param $array
     */
    protected function writeToFile($path, $string, $mode = 'a'): void
    {
        $file = fopen($path, $mode);
        fwrite($file, $string . "\r\n");
        fclose($file);
    }
}


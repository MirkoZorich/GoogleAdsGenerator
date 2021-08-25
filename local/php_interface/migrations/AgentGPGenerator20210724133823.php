<?php

namespace Sprint\Migration;


class AgentGPGenerator20210724133823 extends Version
{
    protected $description = "";

    protected $moduleVersion = "3.12.12";

    /**
     * @throws Exceptions\HelperException
     * @return bool|void
     */
    public function up()
    {
        $helper = $this->getHelperManager();
        $helper->Agent()->saveAgent(array (
            'MODULE_ID' => 'example',
            'USER_ID' => NULL,
            'SORT' => '100',
            'NAME' => 'Example\\Agents\\GenerateGooglePrice::run();',
            'ACTIVE' => 'Y',
            'NEXT_EXEC' => '20.07.2021 14:00:03',
            'AGENT_INTERVAL' => '86400',
            'IS_PERIOD' => 'N',
            'RETRY_COUNT' => '0',
        ));
    }

    public function down()
    {
        //your code ...
    }
}


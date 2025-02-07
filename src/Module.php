<?php
namespace Box\Mod\QrisPayment;

class Module implements \Box\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function install()
    {
        // Register payment gateway
        $this->di['db']->exec("INSERT INTO pay_gateway (name, gateway, enabled, config) VALUES ('QRIS Interactive', 'QrisPayment', 1, '')");
        return true;
    }

    public function uninstall()
    {
        // Remove payment gateway
        $this->di['db']->exec("DELETE FROM pay_gateway WHERE gateway = 'QrisPayment'");
        return true;
    }

    public function getPaymentAdapter($config)
    {
        return new QrisPaymentGateway($config);
    }
}


<?php

class Ps_DemoCQRSHookUsage extends Module
{
    public function __construct()
    {
        $this->name = 'ps_democqrshooksusage';
        $this->version = '1.0.0';
        $this->author = 'Tomas Ilginis';

        parent::__construct();

        $this->displayName = 'Demo for CQRS and hooks usage';
        $this->description =
            'Help developers to understand how to create module using new hooks and apply best practices when using CQRS';

        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_,
        ];
    }
}

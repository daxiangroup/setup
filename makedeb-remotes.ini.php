<?php
return array(
    //-----[ Setup Packages ]
    'hydrogen' => array(
        'type'        => 'setup',
        'url'         => 'https://github.com/daxiangroup/setup/archive/',
        'label'       => 'hydrogen',
        'tag'         => 'master',
        'description' => 'Daxian Group HQ',
    ),
    'lithium' => array(
        'type'        => 'setup',
        //'url'         => 'https://github.com/daxiangroup/setup/archive/',*/
        'url'         => 'https://github.com/daxiangroup/setup.git',
        'label'       => 'lithium',
        'tag'         => 'master',
        'description' => 'Daxian Group Webserver - lithium',
    ),

    //-----[ Site Packages ]
    'daxiangroup-corporate' => array(
        'type'  => 'site',
        'url'   => 'https://github.com/daxiangroup/daxiangroup/archive/',
        'label' => 'daxiangroup',
        'tag'   => 'corporate-1',
        'role'  => 'lithium',
    ),
    'adrienneskitchen' => array(
        'type'  => 'site',
        'url'   => 'https://github.com/daxiangroup/adrienneskitchen/archive/',
        'label' => 'adrienneskitchen',
        'tag'   => 'master',
        'role'  => 'lithium',
        //'deps'  => 'apache2, php5, php-mysql',
    ),
    'thelastprophet' => array(
        'type'        => 'site',
        'url'         => 'https://github.com/daxiangroup/thelastprophet.git',
        'label'       => 'thelastprophet',
        'tag'         => 'master',
        'role'        => 'lithium',
        'description' => 'Landing page for a novella'
    ),
);

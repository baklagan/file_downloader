<?php
namespace Bot;
return array(
    'router' => array(
        'routes' => array(
            'schedule' => array(
                'options' => array(
                    'route'    => 'schedule <fileList>',
                    'defaults' => array(
                        'controller' => 'Bot\Controller\Index',
                        'action'     => 'schedule'
                    )
                )
            ),
            'download' => array(
                'options' => array(
                    'route'    => 'download',
                    'defaults' => array(
                        'controller' => 'Bot\Controller\Index',
                        'action'     => 'download'
                    )
                )
            ),
        )
    )
);
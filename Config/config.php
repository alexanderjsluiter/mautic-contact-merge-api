<?php

declare(strict_types=1);

return [
    'name' => 'Mautic Contact Merge API',
    'description' => 'Provides API endpoint to merge contacts.',
    'author' => 'Alexander Sluiter',
    'version' => '1.0.0',
    // Define routes.
    'routes' => [
        'api' => [
            'mautic_api_contacts_merge' => [
                'path' => '/contacts/{id}/merge',
                'controller' => 'MauticPlugin\MauticContactMergeApiBundle\Controller\Api\ContactMergeApiController::mergeContactsAction',
                'method' => 'POST'
            ],
        ],
    ],
];
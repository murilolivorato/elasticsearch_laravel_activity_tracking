<?php

namespace App\Console\Commands\CreateIndex;

use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class LogActivityToElasticsearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create-index:activity-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create optimized index for activity logging with advanced mappings';
    /**
     * The name of the index.
     *
     * @var string
     */
    protected $indexName = 'activity-logs';

    /**
     * The Elasticsearch service instance.
     *
     * @var ElasticsearchService
     */
    protected $elasticsearchService;

    public function __construct(ElasticsearchService $elasticsearchService)
    {
        parent::__construct();
        $this->elasticsearchService = $elasticsearchService;
    }

    public function handle()
    {
        if ($this->elasticsearchService->indexExists($this->indexName)) {
            echo "Index '{$this->indexName}' already exists.\n";
            return;
        }

        $settings = [
            'number_of_shards' => 1,
            'number_of_replicas' => 1
        ];

        $mapping = [
            'properties' => [
                'model' => ['type' => 'keyword'],
                'model_id' => ['type' => 'keyword'],
                'event' => ['type' => 'keyword'],
                'attributes' => ['type' => 'object',
                    'enabled' => true,
                    'dynamic' => true],
                'changes' => [
                    'type' => 'object',
                    'enabled' => true,
                    'dynamic' => true,
                    'properties' => [
                        'field' => ['type' => 'keyword'],
                        'before' => ['type' => 'keyword', 'null_value' => 'NULL'],
                        'after' => ['type' => 'keyword', 'null_value' => 'NULL']
                    ]
                ],
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'name' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256
                                ]
                            ]
                        ],
                        'type' => ['type' => 'keyword'],
                        'email' => [
                            'type' => 'keyword',
                            'fields' => [
                                'text' => ['type' => 'text']
                            ]
                        ]
                    ]
                ],
                'context' => [
                    'type' => 'object',
                    'properties' => [
                        'ip' => ['type' => 'ip'],
                        'user_agent' => [
                            'type' => 'text',
                            'fields' => ['keyword' => ['type' => 'keyword']]
                        ],
                        'request_id' => ['type' => 'keyword'],
                        'session_id' => ['type' => 'keyword'],
                        'route' => ['type' => 'keyword'],
                        'method' => ['type' => 'keyword']
                    ]
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'environment' => ['type' => 'keyword'],
                        'application_version' => ['type' => 'keyword'],
                        'database_transaction_id' => ['type' => 'keyword']
                    ]
                ],
                'tags' => [
                    'type' => 'keyword'
                ],
                'timestamp' => [
                    'type' => 'date',
                    'format' => 'strict_date_time||strict_date_time_no_millis||epoch_millis'
                ]
            ]
        ];

        $this->elasticsearchService->createIndex($this->indexName, $settings, $mapping);
        echo "Index '{$this->indexName}' created successfully.\n";
    }
}
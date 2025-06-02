<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ElasticsearchService;
class SendActivityToElasticsearch implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $activityData;

    public function __construct(array $activityData)
    {
        $this->activityData = $activityData;
    }

    public function handle()
    {
        $elasticsearchService = new ElasticsearchService();
        $elasticsearchService->populateIndex(
            'activity-logs',
            [
                'id'      => $this->activityData['id'] ?? null,
                'model'      => $this->activityData['model'] ?? null,
                'model_id'   => $this->activityData['model_id'] ?? null,
                'event'      => $this->activityData['event'] ?? null,
                'user'       => $this->activityData['user'] ?? null,
                'attributes' => $this->activityData['attributes'] ?? null,
                'changes'    => $this->activityData['changes'] ?? null,
                'context'         => $this->activityData['context'] ?? null,
                'metadata'         => $this->activityData['metadata'] ?? null,
                'tags'         => $this->activityData['tags'] ?? null,
                'timestamp'  => $this->activityData['timestamp'] ?? null,
            ]
        );
    }
}
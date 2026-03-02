<?php

declare(strict_types=1);

namespace PayzCore\Resources;

use PayzCore\HttpClient;

class Projects
{
    private HttpClient $client;

    public function __construct(HttpClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new project (requires master key).
     *
     * @param array{
     *     name: string,
     *     slug: string,
     *     webhookUrl?: string,
     *     metadata?: array<string, mixed>
     * } $params
     * @return array{
     *     success: true,
     *     project: array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *         apiKey: string,
     *         webhookSecret: string,
     *         webhookUrl: ?string,
     *         createdAt: string
     *     }
     * }
     */
    public function create(array $params): array
    {
        $body = [
            'name' => $params['name'],
            'slug' => $params['slug'],
        ];

        if (isset($params['webhookUrl'])) {
            $body['webhook_url'] = $params['webhookUrl'];
        }
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
        }

        $raw = $this->client->post('/v1/projects', $body);
        $project = $raw['project'];

        return [
            'success' => true,
            'project' => [
                'id' => $project['id'],
                'name' => $project['name'],
                'slug' => $project['slug'],
                'apiKey' => $project['api_key'],
                'webhookSecret' => $project['webhook_secret'],
                'webhookUrl' => $project['webhook_url'] ?? null,
                'createdAt' => $project['created_at'],
            ],
        ];
    }

    /**
     * List all projects (requires master key).
     *
     * @return array{
     *     success: true,
     *     projects: array<array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *         apiKey: string,
     *         webhookUrl: ?string,
     *         isActive: bool,
     *         createdAt: string
     *     }>
     * }
     */
    public function list(): array
    {
        $raw = $this->client->get('/v1/projects');

        $projects = array_map(fn(array $p): array => [
            'id' => $p['id'],
            'name' => $p['name'],
            'slug' => $p['slug'],
            'apiKey' => $p['api_key'],
            'webhookUrl' => $p['webhook_url'] ?? null,
            'isActive' => (bool)$p['is_active'],
            'createdAt' => $p['created_at'],
        ], $raw['projects']);

        return [
            'success' => true,
            'projects' => $projects,
        ];
    }
}

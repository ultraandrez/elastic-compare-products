<?php

namespace App\Service;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;

class ElasticService
{
	/* клиент для подключения к эластику*/
	private Client $client;
	
	private const INDEX_NAME = 'products';

	/**
	 * @throws AuthenticationException
	 */
	function __construct()
	{
		$this->client = ClientBuilder::create()
			->setHosts([$_ENV['ELASTICSEARCH_URL']])
			->build();
	}

	/**
	 * Создание индекса
	 * 
	 * @return array
	 */
	public function createIndex(): array
	{
		$result = ['success' => true, 'error' => ''];
		$params = [
			'index' => self::INDEX_NAME,
			'body' => [
				"mappings" => [
					"properties" => [
						"code" => [
							"type" => "text"
						],
						"name" => [
							"type" => "text",
							"analyzer" => "name_analyzer",
							"search_analyzer" => "standard"
						],
						"price" => [
							"type" => "float"
						]
					]
				],
				"settings" => [
					"index" => [
						"analysis" => [
							"analyzer" => [
								"name_analyzer" => [
									"type" => "custom",
									"tokenizer" => "standard",
									"char_filter" => [
										"html_strip",
										"comma_to_dot_char_filter",
									],
									"filter" => [
										"mynGram"
									]
								]
							],
							"char_filter" => [
								"comma_to_dot_char_filter" => [
									"type" => "mapping",
									"mappings" => [
										", => ."
									]
								]
							],
							"filter" => [
								"mynGram" => [
									"type" => "ngram",
									"min_gram" => 2,
									"max_gram" => 50
								]
							]
						],
						"max_ngram_diff" => "50"
					],
				]
			]
		];

		try {
			$response = $this->client->indices()->create($params)->asArray();
		} catch (\Throwable $e) {
			$result['error'] =  $e->getMessage();
		}

		if (isset($response) && $response['acknowledged'])
			$result['success'] = true;
		return $result;
	}

	/**
	 * Множественная индексация документов
	 * 
	 * @param array $bulkRows
	 * @return array
	 */
	public function bulkIndex(array $bulkRows): array
	{
		$result = ['success' => false, 'error'	=> ''];

		$params = [];
		foreach ($bulkRows as $row) {
			$params['body'][] = ['index' => ['_index' => self::INDEX_NAME]];
			$params['body'][] = $row;
		}
		try {
			$this->client->bulk($params)->asArray();
		} catch (\Throwable $e) {
			$result['error'] = $e->getMessage();
			return $result;
		}	
		$result['success'] = true;
		return $result;
	}

	/**
	 * Поиск документов
	 * 
	 * @param string $searchQuery
	 * @return array|mixed
	 */
	public function search(string $searchQuery)
	{
		$params = [
			'index' => self::INDEX_NAME,
			'body'  => [
				'size' => 1000,
				'query' => [
					'match' => [
						'source_name' => [
							'query' => $searchQuery,
						],
					],
				],
				
			]
		];
		try {
			$response = $this->client->search($params)->asArray();
		} catch (\Throwable $e) {
			// логирование
			return [];
		}

		return $response['hits']['hits'];
	}

	/**
	 * Удаление индекса
	 * 
	 * @return array
	 * @throws ServerResponseException
	 * @throws \Elastic\Elasticsearch\Exception\MissingParameterException
	 */
	public function deleteIndex(): array
	{
		$result = ['success' => false, 'error'	=> ''];
		try {
			$params = ['index' => self::INDEX_NAME];
			$response = $this->client->indices()->delete($params)->asArray();
		} catch (ClientResponseException $e) {
			if ($e->getCode() === 404) {
				$result['error'] = 'Документа не существует';
				return $result;
			}
		}
		if (isset($response) && $response['acknowledged']) {
			$result['success'] = true;
			return $result;
		}
		return $result;
	}
}
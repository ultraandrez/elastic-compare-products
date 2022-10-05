<?php

namespace App\Service;

use JetBrains\PhpStorm\ArrayShape;

class CatalogProductService
{
	/**
	 * Паттерн разбиения продукта на части КЛАСС-НАЗВАНИЕ-ЦВЕТ-КОЛИЧЕСТВО
	 */
	const PRODUCT_PATTERN = '/(?:(LUX|PREMIUM|STANDARD|BASIC).*)?(?:(?:\(([а-яА-Яё -]+?(?:[A-Z0-9]+)?)\))|(?:\/ ?([а-яА-Яё -]+))), (упак|шт|рул)/u';
	
	private ExcelParserService $excelParserService;
	private ElasticService $elasticService;
	
	function __construct()
	{
		$this->excelParserService = new ExcelParserService();
		$this->elasticService = new ElasticService();
	}

	/**
	 * Обработка запроса на поиск товара
	 * 
	 * @param $searchQuery - параметры запросв
	 * @return array
	 */
	public function processSearchQuery($searchQuery): array
	{
		$foundData = $this->elasticService->search($searchQuery);
		$result = [];
		foreach ($foundData as $data) {
			$result[] = [
				'code' => $data['_source']['code'],
				'name' => $data['_source']['source_name']
			];
		}
		return $result;
	}

	/**
	 * Парсинг csv документа в документы elasticsearch 
	 * 
	 * @param string $fileName - название документа 
	 * @return array
	 */
	public function parseCsvToElasticDocument(string $fileName): array {
		$result = ['success' => false, 'error' => ''];
		if (!$this->excelParserService::isFileExists($fileName)) {
			$result['error'] = 'Файла не существует';
			return $result;
		}
		$descriptor = $this->excelParserService::openFile($fileName, 'r');
		$iterator = $this->excelParserService::readLine($descriptor);

		$numberFormatter = new \NumberFormatter("pt-PT", \NumberFormatter::DECIMAL);
		$bulkRows = [];

		foreach ($iterator as $iteration) {
			if (!$iteration[0] || !$iteration[1] || !$iteration[2])
				continue;
			$price = $numberFormatter->parse($iteration[2]);
			$productName = $iteration[1];
			$additionalProductProps = $this->parseProductName($productName);
			$bulkRow = [
				'code'	=> $iteration[0],
				'source_name' 	=> $productName,
				'price' => $price
			];
			$bulkRow += $additionalProductProps;
			$bulkRows[] = $bulkRow;
		}

		$bulkResult = $this->elasticService->bulkIndex($bulkRows);
		$result = [
			'success' => $bulkResult['success'],
			'error' => $bulkResult['error']
		];
		$this->excelParserService::closeFile($descriptor);
		return $result;
	}

	/**
	 * Обработка названия товара. Вернет информацию о нем(очищенное имя, класс, цвет, в чем измеряется)
	 * 
	 * @param string $productName
	 * @return array
	 */
	private function parseProductName(string $productName): array
	{
		$result = [];
		$productProperties = RegexService::regexMatches(self::PRODUCT_PATTERN, $productName);

		$clearProductName = str_replace('Döcke ', '', $productName);
		
		if ($productProperties) {
			$result = [
				'class' => $productProperties[1],
				'color' => $productProperties[2] ?: $productProperties[3],
				'unit' => $productProperties[4],
			];
		}
		$result['clear_name'] = $clearProductName;
		return $result;
	}
}
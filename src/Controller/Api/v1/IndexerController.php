<?php

namespace App\Controller\Api\v1;

use App\Service\CatalogProductService;
use App\Service\ElasticService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * Class IndexerController
 * @package App\Controller\Api\v1
 * @Route("/api", name="product")
 */
class IndexerController extends AbstractController
{
	private ElasticService $elasticService;
	private CatalogProductService $catalogProductService;
	
	function __construct()
	{
		$this->elasticService = new ElasticService();
		$this->catalogProductService = new CatalogProductService();
	}
	
	/**
	 * @param Request $request
	 * @return JsonResponse
	 * @Route("/products/search", name="products", methods={"GET"})
	 */
	public function getSearchProducts(Request $request): Response
	{
		$searchQuery = $request->query->get('q') ?? '';
		$products = $this->catalogProductService->processSearchQuery($searchQuery);
		return new Response(
			json_encode($products),
			200,
			['Content-Type' => 'application/json']
		);
	}
	
	/**
	 * @return JsonResponse
	 * @Route("/products/create", name="products_create", methods={"GET"})
	 */
	public function createAndIndexProducts(Request $request): Response
	{
		$fileName = $request->query->get('filename');
		$result = ['success' => false];
		$uploadsDir = $this->getParameter('uploads_dir');
		$documentPath = $uploadsDir . '/files/' . $fileName;
		$createIndexResult = $this->elasticService->createIndex();
		
		if ($createIndexResult['success'])
			$productsIndexResult = $this->catalogProductService->parseCsvToElasticDocument($documentPath);
		else
			$result['error'] = $createIndexResult['error'];
		
		if (isset($productsIndexResult))
			$result = $productsIndexResult;
		return new Response(
			json_encode($result),
			200,
			['Content-Type' => 'application/json']
		);
	}
}

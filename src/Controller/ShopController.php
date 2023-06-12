<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShopController extends AbstractController
{
    private $db;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->db = $entityManager;
    }

    #[Route('/', name: 'app_shop')]
    public function index(): Response
    {
        $products = $this->db->getRepository(Product::class)->findAll();
        $total_products = $this->db->getRepository(Product::class)->count([]);

        return $this->render('shop/index.html.twig', [
            'title' => 'Welcome to Tsonko\'s Shop !',
            'entities' => $products,
            'total_products' => $total_products,
        ]);
    }
}

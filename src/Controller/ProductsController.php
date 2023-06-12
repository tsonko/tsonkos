<?php

namespace App\Controller;

use App\Entity\Product;
use phpDocumentor\Reflection\Types\Integer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Config\Framework\RequestConfig;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ProductsController extends AbstractController
{
    private $db;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->db = $entityManager;
    }

    #[Route('/products/{disabled}', name: 'app_products')]
    public function index (int $disabled = null): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');

        if($disabled!=0 && $disabled!=1)
            return $this->redirectToRoute('app_products_overview');

        if ($disabled !== null) {
            $products = $this->db->getRepository(Product::class)->findBy(['disabled' => $disabled]);
            $total_products = $this->db->getRepository(Product::class)->count(['disabled' => $disabled]);
        } else {
            $products = $this->db->getRepository(Product::class)->findAll();
            $total_products = $this->db->getRepository(Product::class)->count([]);
        }


        return $this->render('products/index.html.twig', array(
            'title' => 'Edit product',
            'entities' => $products,
            'total_products' => $total_products,
        ));
    }

    #[Route('/products/new', name: 'app_products_new')]
    public function new(Request $request): Response
    {

        // TODO
        // USE THE CODE FROM THE EDIT FUNCTION


        $product = new Product();
        $product->setName('');

        $form = $this->createFormBuilder($product)
            ->add('name', TextType::class)
            ->add('save', SubmitType::class, ['label' => 'Create product'])
            ->getForm();


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated

            $product = new Product();
            $product->setName($form['name']->getData());
            $this->db->persist($product);
            $this->db->flush();

            return $this->redirectToRoute('app_products_overview');

        }

        return $this->render('products/new.html.twig', [
            'form' => $form,
        ]);


    }

    #[Route('/products/edit/{product_id}', name: 'app_products_edit')]
    public function edit(Request $request,int $product_id, SluggerInterface $slugger): Response
    {

        // TODO
        // MOVE THE UPLOADER TO THE SEPARATE CLASS
        // USE THE SAME FORM IN ADD AND EDIT

        $product = $this->db->getRepository(Product::class)->find($product_id);

        if (!$product) {
            throw $this->createNotFoundException(
                'No product found for id '.$product_id
            );
        }

        $form = $this->createFormBuilder($product)
            ->add('name', TextType::class)
            ->add('description', TextType::class)
            ->add('image', FileType::class, [
                'label' => 'Product Image (jpg|png)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image',
                    ])
                ],
            ])
            ->add('save', SubmitType::class, ['label' => 'Save changes'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $product->setName($form['name']->getData());
            $product->setDescription($form['description']->getData());
            $image = $form->get('image')->getData();

            if ($image) {

                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);

                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$image->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $image->move(
                        $this->getParameter('product_images_directory'),
                        $newFilename
                    );

                    if ($product->getImage()) {
                        $imagePath =  $this->getParameter('product_images_directory').$product->getImage();
                        unlink('./'.$imagePath);
                    }

                } catch (FileException $e) {

                }

                $product->setImage($newFilename);

            }

            $this->db->flush();
            return $this->redirectToRoute('app_products_overview');

        }

        return $this->render('products/edit.html.twig', [
            'title' => 'Edit product',
            'image' => $product->getImage(),
            'form' => $form,
            'product_id' => $product->getId(),
        ]);
    }

    #[Route('/products/delete/{product_id}', name: 'app_products_delete')]
    public function delete(int $product_id): Response
    {

        $product = $this->db->getRepository(Product::class)->find($product_id);

        $filesystem = new Filesystem();

        if ($product->getImage()) {
            $imagePath =  $this->getParameter('product_images_directory').$product->getImage();
            if(file_exists($imagePath))
                unlink('./'.$imagePath);
        }

        $this->db->remove($product);
        $this->db->flush();

        return $this->render('products/delete.html.twig', [
            'title' => 'Delete product'
        ]);
    }

    #[Route('/products/delete_image/{product_id}', name: 'app_products_delete_image')]
    public function delete_image(int $product_id): Response
    {

        $product = $this->db->getRepository(Product::class)->find($product_id);

        $filesystem = new Filesystem();

        if ($product->getImage()) {
            $imagePath =  $this->getParameter('product_images_directory').$product->getImage();
            if(file_exists($imagePath))
                unlink('./'.$imagePath);
        }

        $product->setImage("");
        $this->db->flush();


        return $this->redirectToRoute('app_products_edit',['product_id'=>$product_id]);
    }
}
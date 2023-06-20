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

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->productRepository = $entityManager->getRepository(Product::class);
    }

    #[Route('/products', name: 'app_products')]
    public function index (): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');


        $products = $this->productRepository->findAll();
        $total_products = $this->productRepository->count([]);


        return $this->render('products/index.html.twig', array(
            'title' => 'Edit product',
            'entities' => $products,
            'total_products' => $total_products,
        ));
    }

    #[Route('/products/new', name: 'app_products_new')]
    public function new(Request $request,EntityManagerInterface $entityManager): Response
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
            $this->productRepository->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute('app_products');

        }

        return $this->render('products/new.html.twig', [
            'form' => $form,
        ]);


    }

    #[Route('/products/edit/{product_id}', name: 'app_products_edit')]
    public function edit(Request $request,int $product_id, SluggerInterface $slugger,EntityManagerInterface $entityManager): Response
    {

        // TODO
        // MOVE THE UPLOADER TO THE SEPARATE CLASS
        // USE THE SAME FORM IN ADD AND EDIT

        $product = $this->productRepository->find($product_id);

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

            $entityManager->flush();
            return $this->redirectToRoute('app_products');

        }

        return $this->render('products/edit.html.twig', [
            'title' => 'Edit product',
            'image' => $product->getImage(),
            'form' => $form,
            'product_id' => $product->getId(),
        ]);
    }

    #[Route('/products/delete/{product_id}', name: 'app_products_delete')]
    public function delete(int $product_id,EntityManagerInterface $entityManager): Response
    {

        $product = $this->productRepository->find($product_id);

        $filesystem = new Filesystem();

        if ($product->getImage()) {
            $imagePath =  $this->getParameter('product_images_directory').$product->getImage();
            if (file_exists('./'.$imagePath))
                unlink('./'.$imagePath);
        }

        $entityManager->remove($product);
        $entityManager->flush();

        return $this->render('products/delete.html.twig', [
            'title' => 'Delete product'
        ]);
    }

    #[Route('/products/delete_image/{product_id}', name: 'app_products_delete_image')]
    public function delete_image(int $product_id,EntityManagerInterface $entityManager): Response
    {

        $product = $this->productRepository->find($product_id);

        $filesystem = new Filesystem();

        if ($product->getImage()) {
            $imagePath =  $this->getParameter('product_images_directory').$product->getImage();
            if(file_exists($imagePath))
                unlink('./'.$imagePath);
        }

        $product->setImage("");
        $entityManager->flush();


        return $this->redirectToRoute('app_products_edit',['product_id'=>$product_id]);
    }
}
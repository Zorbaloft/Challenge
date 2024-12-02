<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Image;
use App\Form\ImageType;
use App\Form\CategoryType;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Imagine\Gd\Imagine;
use Symfony\UX\Cropperjs\Factory\CropperInterface;
use Symfony\UX\Cropperjs\Form\CropperType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class BaseController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'home')]
    public function upload(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ImageType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            $category = $form->get('category_id')->getData();

            if ($imageFile) {
                $filename = uniqid() . '.' . $imageFile->guessExtension();
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';

                try {

                    $imageFile->move($uploadDir, 'temp_' . $filename);

                    return $this->redirectToRoute('crop_image', ['fileName' => 'temp_' . $filename, 'category' => $category->getId()]);
                } catch (\Exception $e) {
                    return $this->render('home.html.twig', [
                        'form' => $form->createView(),

                    ]);
                }
            }
        } else if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Invalid file format or file size too large.');
            return $this->redirectToRoute('home');
        }

        $categories = $this->entityManager->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.images', 'i')
            ->addSelect('i') // Explicitly load the images
            ->getQuery()
            ->getResult();

        // Create the category form
        $categoryForm = $this->createForm(CategoryType::class);
        $categoryForm->handleRequest($request);

        if ($categoryForm->isSubmitted() && $categoryForm->isValid()) {
            $category = $categoryForm->getData();
            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'Category created successfully!');
            return $this->redirectToRoute('home');
        }

        return $this->render('home.html.twig', [
            'form' => $form->createView(),
            'categoryForm' => $categoryForm->createView(),
            'categories' => $categories,
        ]);
    }

    #[Route('/crop/{fileName}/{category}', name: 'crop_image')]
    public function crop(CropperInterface $cropper, Request $request, string $fileName, int $category, EntityManagerInterface $em): Response
    {
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $filePath = $uploadDir . '/' . $fileName;
        $publicUrl = '/uploads/' . $fileName; // Correctly set the public URL

        // Ensure the file exists
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('The file does not exist');
        }

        $crop = $cropper->createCrop($filePath);
        $crop->setCroppedMaxSize(2000, 1500);

        $form = $this->createFormBuilder(['crop' => $crop])
            ->add('crop', CropperType::class, [
                'public_url' => $publicUrl,
                'cropper_options' => [
                    'aspectRatio' => 2000 / 1500,
                ],
            ])
            ->add('fileName', HiddenType::class, [
                'data' => $fileName,
            ])
            ->add('category', HiddenType::class, [
                'data' => $category,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the cropped image data (as a string)
            $croppedImage = $crop->getCroppedImage();

            // Save the cropped image temporarily
            $croppedFileName = 'cropped_' . $fileName;
            $croppedFilePath = $uploadDir . '/' . $croppedFileName;
            file_put_contents($croppedFilePath, $croppedImage);

            // Compress the image to a maximum size of 200 KB
            $imagine = new Imagine();
            $image = $imagine->open($croppedFilePath);
            $quality = 90;

            while (filesize($croppedFilePath) > 200 * 1024 && $quality > 10) {
                $image->save($croppedFilePath, ['jpeg_quality' => $quality]);
                $quality -= 5;
            }

            // Delete the original image
            unlink($filePath);

            // Create and persist the Image entity
            $imageEntity = new Image();
            $imageEntity->setFilePath($croppedFileName)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setCategory($this->entityManager->getRepository(Category::class)->find($category));

            $em->persist($imageEntity);
            $em->flush();

            $this->addFlash('success', 'Image cropped, compressed, and uploaded successfully!');
            return $this->redirectToRoute('home');
        }

        return $this->render('crop.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

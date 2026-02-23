<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AdminUserRolesType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class UserAdminController extends AbstractController
{
    #[Route('', name: 'admin_users_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $em->getRepository(User::class)->findAll();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/{id}/roles', name: 'admin_users_roles', requirements: ['id' => '\d+'])]
    public function editRoles(
        User $user,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AdminUserRolesType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // guard: ماتخليش user يبقى بلا ROLE_USER
            if (!in_array('ROLE_USER', $user->getRoles(), true)) {
                $user->setRoles(array_merge($user->getRoles(), ['ROLE_USER']));
            }

            $em->flush();

            $this->addFlash('success', 'Roles updated ✅');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/roles.html.twig', [
            'u' => $user,
            'form' => $form->createView(),
        ]);
    }
}
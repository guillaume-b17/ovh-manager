<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // 🔐 Si utilisateur connecté → redirection vers EasyAdmin
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        // 🧭 Sinon → redirection vers login
        return $this->redirectToRoute('app_login');
    }
}

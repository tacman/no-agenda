<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EpisodeSearchController extends AbstractController
{
    #[Route('/search', name: 'episode_search', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('search/episodes.html.twig');
    }
}

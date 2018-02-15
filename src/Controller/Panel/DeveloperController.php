<?php

namespace App\Controller\Panel;

use App\Entity\OAuthClient;
use App\Helper\AccountHelper;
use App\Form\CreateDevAccount;
use App\Form\CreateDevApp;
use App\Helper\TokenGenerator;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DeveloperController extends Controller
{
    public static function __setupNavigation(ContainerInterface $container)
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $container->get('security.token_storage')->getToken()->getUser();

        return [
            [
                'type'    => 'group',
                'parent'  => 'root',
                'id'      => 'developer',
                'title'   => 'Developer',
                'icon'    => 'fa fa-fw fa-code',
                'display' => $currentUser->getDeveloperStatus() ? true : false,
            ],
            [
                'type'   => 'link',
                'parent' => 'developer',
                'id'     => 'developer_create_application',
                'title'  => 'Create new application',
                'href'   => 'developer-create-application',
                'view'   => 'DeveloperController::createApplication',
            ],
            [
                'type'   => 'link',
                'parent' => 'developer',
                'id'     => 'developer_applications',
                'title'  => 'Your applications',
                'href'   => 'developer-applications',
                'view'   => 'DeveloperController::applicationList',
            ],
            [
                'type'   => 'link',
                'parent' => 'null',
                'id'     => 'developer_show_application',
                'title'  => 'Show application',
                'href'   => 'developer-show-application',
                'view'   => 'DeveloperController::showApplication',
            ],
            [
                'type'    => 'link',
                'parent'  => 'root',
                'id'      => 'create_developer_account',
                'title'   => 'Create developer account',
                'href'    => 'developer-register',
                'view'    => 'DeveloperController::register',
                'display' => $currentUser->getDeveloperStatus() ? false : true,
            ],
        ];
    }

    public static function __callNumber()
    {
        return 40;
    }

    public function createApplication(Request $request, $navigation)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ((int)$user->getDeveloperStatus() != 1) {
            header('Location: '.$this->generateUrl('panel', ['page' => 'developer-register']));
            exit;
        }

        $createAppForm = $this->createForm(CreateDevApp::class, null, ['entity_manager' => $em]);

        $createAppForm->handleRequest($request);
        if ($createAppForm->isSubmitted() && $createAppForm->isValid()) {

            AccountHelper::addApp(
                $em,
                $createAppForm->get('client_name')->getData(),
                TokenGenerator::createRandomToken(['use_openssl' => false]),
                $createAppForm->get('redirect_uri')->getData(),
                $createAppForm->get('scopes')->getData(),
                $user->getId()
            );

            header('Location: '.$this->generateUrl('panel', ['page' => 'developer-applications']));
            exit;
        }

        return $this->render('panel/developer-create-applications.html.twig', [
            'navigation_links' => $navigation,
            'create_app_form'  => $createAppForm->createView(),
        ]);
    }

    public function applicationList($navigation)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user->getDeveloperStatus()) {
            header('Location: '.$this->generateUrl('panel', ['page' => 'developer-register']));
            exit;
        }

        /** @var \App\Entity\OAuthClient[] $apps */
        $apps = $em->getRepository(OAuthClient::class)->findBy(['user_id' => $user->getId()]);

        return $this->render('panel/developer-list-applications.html.twig', [
            'navigation_links' => $navigation,
            'app_list'         => $apps,
        ]);
    }

    public function showApplication(Request $request, $navigation)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ((int)$user->getDeveloperStatus() != 1) {
            header('Location: '.$this->generateUrl('panel', ['page' => 'developer-register']));
            exit;
        }

        if (!$request->query->has('app')) {
            header('Location: '.$this->generateUrl('panel', ['page' => 'developer-applications']));
            exit;
        }
        $appId = $request->query->get('app');
        /** @var \App\Entity\OAuthClient $appData */
        $appData = $em->getRepository(OAuthClient::class)->findOneBy(['client_identifier' => $appId]);

        if (is_null($appData)) {
            return $this->render('panel/developer-app-not-found.html.twig', [
                'navigation_links' => $navigation,
            ]);
        }

        return $this->render('panel/developer-show-app.html.twig', [
            'navigation_links' => $navigation,
            'app_data'         => $appData,
        ]);
    }

    public function register(Request $request, $navigation)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ((int)$user->getDeveloperStatus() == 1) {
            header('Location: '.$this->generateUrl('panel', ['page' => 'developer-applications']));
            exit;
        }

        $developerForm = $this->createForm(CreateDevAccount::class);

        $developerForm->handleRequest($request);
        if ($developerForm->isSubmitted()) {
            $user->setDeveloperStatus(true);
            $em->flush();
            header('Location: '.$this->generateUrl('panel', ['page' => 'developer-applications']));
            exit;
        }

        return $this->render('panel/developer-register.html.twig', [
            'navigation_links' => $navigation,
            'developer_form'   => $developerForm->createView(),
        ]);
    }
}
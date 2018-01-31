<?php

namespace App\Controller;

use App\Entity\OAuthAccessToken;
use App\Entity\OAuthAuthorizationCode;
use App\Entity\OAuthClient;
use App\Entity\OAuthRefreshToken;
use App\Entity\OAuthScope;
use App\Entity\User;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\GrantType\RefreshToken;
use OAuth2\OpenID\GrantType\AuthorizationCode;
use OAuth2\Request as OAuthRequest;
use OAuth2\Response;
use OAuth2\Response as OAuthResponse;
use OAuth2\Scope;
use OAuth2\Server;
use OAuth2\Storage\Memory;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class OAuthController extends Controller
{
    /** @var \OAuth2\Server $oauthServer */
    private $oauthServer = null;

    public function oauthServer()
    {
        $em = $this->getDoctrine()->getManager();

        /** @var \App\Repository\OAuthClientRepository $clientStorage */
        $clientStorage = $em->getRepository(OAuthClient::class);
        /** @var \App\Repository\UserRepository $userStorage */
        $userStorage = $em->getRepository(User::class);
        /** @var \App\Repository\OAuthAccessTokenRepository $accessTokenStorage */
        $accessTokenStorage = $em->getRepository(OAuthAccessToken::class);
        /** @var \App\Repository\OAuthAuthorizationCodeRepository $authorizationCodeStorage */
        $authorizationCodeStorage = $em->getRepository(OAuthAuthorizationCode::class);
        /** @var \App\Repository\OAuthRefreshTokenRepository $refreshTokenStorage */
        $refreshTokenStorage = $em->getRepository(OAuthRefreshToken::class);

        // Pass the doctrine storage objects to the OAuth2 server class
        $this->oauthServer = new Server(array(
            'client_credentials' => $clientStorage,
            'user_credentials'   => $userStorage,
            'access_token'       => $accessTokenStorage,
            'authorization_code' => $authorizationCodeStorage,
            'refresh_token'      => $refreshTokenStorage,
        ), array(
            'refresh_token_lifetime' => 2419200,
        ));

        // Get all SCOPES
        /** @var \App\Entity\OAuthScope[] $scopesList */
        $scopesList = $em->getRepository(OAuthScope::class)->findAll();

        $defaultScope = '';
        $supportedScopes = [];

        foreach ($scopesList as $scope) {
            if ($scope->isDefault()) {
                $defaultScope = $scope->getScope();
            }
            $supportedScopes[] = $scope->getScope();
        }
        $memory = new Memory([
            'default_scope'    => $defaultScope,
            'supported_scopes' => $supportedScopes,
        ]);
        $scopeUtil = new Scope($memory);
        $this->oauthServer->setScopeUtil($scopeUtil);

        // Add all grant types
        // Add the "Client Credentials" grant type (it is the simplest of the grant types)
        $this->oauthServer->addGrantType(new ClientCredentials($clientStorage));

        // Add the "Authorization Code" grant type (this is where the oauth magic happens)
        $this->oauthServer->addGrantType(new AuthorizationCode($authorizationCodeStorage));

        // Add the "Refresh Token" grant type
        $this->oauthServer->addGrantType(new RefreshToken($refreshTokenStorage, array(
            // the refresh token grant request will have a "refresh_token" field
            // with a new refresh token on each request
            'always_issue_new_refresh_token' => true,
        )));
    }

    public function authorize(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return $this->redirectToRoute('login', ['_target_path' => $request->server->get('REQUEST_URI')]);
        }

        $this->oauthServer();
        $em = $this->getDoctrine()->getManager();

        $requestOAuth = OAuthRequest::createFromGlobals();
        $response = new Response();

        // validate the authorize request
        if (!$this->oauthServer->validateAuthorizeRequest($requestOAuth, $response)) {
            $response->send();
            exit;
        }
        // display an authorization form
        // Get all information about the Client requesting an Auth code
        /** @var \App\Entity\OAuthClient $clientInfo */
        $clientInfo = $em->getRepository(OAuthClient::class)->findOneBy(['client_identifier' => $request->query->get('client_id')]);

        $scopes = [];
        foreach ($clientInfo->getScopes() as $scope) {
            /** @var \App\Entity\OAuthScope $getScope */
            $getScope = $em->getRepository(OAuthScope::class)->findOneBy(['scope' => $scope]);
            if (!is_null($getScope)) {
                $scopes[] = $getScope->getName();
            }
        }
        if ($request->request->count() === 0) {
            return $this->render('oauth-authorize.html.twig', [
                'client_info' => $clientInfo,
                'scopes'      => $scopes,
            ]);
        }

        // print the authorization code if the user has authorized your client
        $is_authorized = $request->request->has('authorized');
        $this->oauthServer->handleAuthorizeRequest($requestOAuth, $response, $is_authorized, $user->getId());
        // if ($is_authorized) {
        //     // this is only here so that you get to see your code in the cURL request. Otherwise, we'd redirect back to the client
        //     $code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=') + 5, 40);
        //     exit("SUCCESS! Authorization Code: $code");
        // }
        $response->send();
        exit;
    }

    // curl http://localhost/oauth/token -d 'grant_type=authorization_code&code=AUTHORIZATION_CODE&client_id=testclient&client_secret=testpass&redirect_uri=http://d3strukt0r.esy.es'
    public function token()
    {
        $this->oauthServer();

        // Handle a request for an OAuth2.0 Access Token and send the response to the client
        $request = OAuthRequest::createFromGlobals();

        /** @var \OAuth2\Response $response */
        $response = $this->oauthServer->handleTokenRequest($request);
        $response->send();
        exit;
    }

    // curl http://localhost/oauth/resource -d 'access_token=YOUR_TOKEN'
    public function resource(Request $request)
    {
        $this->oauthServer();
        $em = $this->getDoctrine()->getManager();
        $requestOAuth = OAuthRequest::createFromGlobals();
        $responseOAuth = new OAuthResponse();

        // Handle a request to a resource and authenticate the access token
        $scopeRequired = $request->query->has('scope') ? $request->query->get('scope') : null;
        if (!$this->oauthServer->verifyResourceRequest($requestOAuth, $responseOAuth, $scopeRequired)) {
            // if the scope required is different from what the token allows, this will send a "401 insufficient_scope" error
            $responseOAuth->send();
            exit;
        }

        $token = $this->oauthServer->getAccessTokenData($requestOAuth);

        /** @var \App\Entity\User $user */
        $user = $em->getRepository(User::class)->findOneBy(['id' => $token['user_id']]);

        if (is_null($token['scope'])) {
            return new JsonResponse();
        }

        $scopeList = explode(' ', $token['scope']);
        $responseData = [];
        // Call the function for all scopes
        foreach ($scopeList as $scope) {
            // Find out the function name
            $functionProcess = explode(':', $scope);
            foreach ($functionProcess as $key => $item) {
                $functionProcess[$key] = ucfirst($item);
            }
            $function = 'scope'.implode('', $functionProcess);

            // Call function
            $data = $this->{$function}($user);
            foreach ($data as $key => $value) {
                $responseData[$key] = $value;
            }
        }
        return new JsonResponse($responseData);
    }

    /**
     * @param \App\Entity\User $user
     *
     * @return array
     */
    private function scopeUserUsername($user)
    {
        return ['username' => $user->getUsername()];
    }

    /**
     * @param \App\Entity\User $user
     *
     * @return array
     */
    private function scopeUserEmail($user)
    {
        return ['email' => $user->getEmail()];
    }

    /**
     * @param \App\Entity\User $user
     *
     * @return array
     */
    private function scopeUserName($user)
    {
        return ['name' => $user->getProfile()->getName()];
    }

    /**
     * @param \App\Entity\User $user
     *
     * @return array
     */
    private function scopeUserSurname($user)
    {
        return ['surname' => $user->getProfile()->getSurname()];
    }

    /**
     * @param \App\Entity\User $user
     *
     * @return array
     */
    private function scopeUserBirthday($user)
    {
        return ['birthday' => $user->getProfile()->getBirthday()->getTimestamp()];
    }

    /**
     * @param \App\Entity\User $user
     *
     * @return array
     */
    private function scopeUserSubscription($user)
    {
        return ['subscription_type' => $user->getSubscription()->getSubscription()->getTitle()];
    }
}
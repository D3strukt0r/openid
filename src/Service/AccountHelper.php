<?php

namespace App\Service;

use App\Entity\OAuthClient;
use App\Entity\OAuthScope;
use App\Entity\SubscriptionType;
use App\Entity\User;
use App\Entity\UserProfiles;
use App\Entity\UserSubscription;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\RequestStack;

class AccountHelper
{
    public static $settings = [
        'username'     => [
            'min_length'    => 3,
            'max_length'    => 50,
            'blocked'       => ['admin', 'administrator', 'mod', 'moderator', 'guest', 'undefined'],
            'blocked_parts' => ['mod', 'system', 'admin'],
            'pattern'       => '/^[a-z0-9_]+$/i', // Accepted: a-z, A-Z, 1-9 and _
        ],
        'password'     => [
            'min_length' => 7,
            'max_length' => 100,
            'salt'       => 'random',
        ],
        'email'        => [
            'pattern' => '/^[a-z0-9_\.-]+@([a-z0-9]+([\-]+[a-z0-9]+)*\.)+[a-z]{2,7}$/i',
        ],
        'subscription' => [
            'default' => 1,
        ],
        'login'        => [
            'session_email'    => 'USER_EM',
            'session_password' => 'USER_PW',
            'cookie_name'      => 'account',
            'cookie_expire'    => '+1 month',
            'cookie_path'      => '/',
            'cookie_domain'    => 'orbitrondev.org',
        ],
    ];

    /**
     * @var \Doctrine\Common\Persistence\ObjectManager $em
     */
    private $em;

    /**
     * @var \Symfony\Component\HttpFoundation\Request $request
     */
    private $request;

    public function __construct(ObjectManager $manager, RequestStack $request)
    {
        $this->em = $manager;
        $this->request = $request->getCurrentRequest();
    }

    /**
     * Add a new user. Username, Email, and password is required twice. Returns
     * the user id.
     *
     * @param string $username
     * @param string $password
     * @param string $passwordVerify
     * @param string $email
     *
     * @return int|string
     */
    public function addUser($username, $password, $passwordVerify, $email)
    {
        // Check username
        if (strlen($username) == 0) {
            return 'username:insert_username';
        } elseif (strlen($username) < self::$settings['username']['min_length']) {
            return 'username:username_short';
        } elseif (strlen($username) > self::$settings['username']['max_length']) {
            return 'username:username_long';
        } elseif ($this->usernameExists($username)) {
            return 'username:user_exists';
        } elseif ($this->usernameBlocked($username)) {
            return 'username:blocked_name';
        } elseif (!$this->usernameValid($username)) {
            return 'username:not_valid_name';
        } // Check E-Mail
        elseif (strlen($email) == 0) {
            return 'email:insert_email';
        } elseif (!$this->emailValid($email)) {
            return 'email:email_not_valid';
        } // Check password
        elseif (strlen($password) == 0) {
            return 'password:insert_password';
        } elseif (strlen($password) < self::$settings['password']['min_length']) {
            return 'password:password_too_short';
        } elseif ($password != $passwordVerify) {
            return 'password_verify:passwords_do_not_match';
        }

        // Add user to database
        $user = (new User())
            ->setUsername($username)
            ->setPassword($password)
            ->setEmail($email)
            ->setEmailVerified(false)
            ->setCreatedOn(new \DateTime())
            ->setLastOnlineAt(new \DateTime())
            ->setCreatedIp($this->request->getClientIp())
            ->setLastIp($this->request->getClientIp())
            ->setDeveloperStatus(false);

        $userProfile = (new UserProfiles())
            ->setUser($user);
        $user->setProfile($userProfile);

        /** @var SubscriptionType $defaultSubscription */
        $defaultSubscription = $this->em->find(SubscriptionType::class, self::$settings['subscription']['default']);

        $userSubscription = (new UserSubscription())
            ->setUser($user)
            ->setSubscription($defaultSubscription)
            ->setActivatedAt(new \DateTime())
            ->setExpiresAt(new \DateTime());
        $user->setSubscription($userSubscription);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @param \App\Entity\User $user
     */
    public function removeUser(User $user)
    {
        $this->em->remove($user);
        $this->em->flush();
    }

    /**
     * Checks whether the username or email exists in the database. Returns
     * true when the username or email exist once in the database.
     *
     * @param string $usernameOrEmail
     *
     * @return \App\Entity\User|bool
     */
    public function userExists($usernameOrEmail)
    {
        /** @var User $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $usernameOrEmail]);
        if (is_null($user)) {
            /** @var User $user */
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $usernameOrEmail]);
            if (is_null($user)) {
                return false;
            }
        }
        return $user;
    }

    /**
     * Checks whether the username is already existing in the database. Returns
     * true when the username is already existing once in the database.
     *
     * @param string $username
     *
     * @return bool
     */
    public function usernameExists($username)
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);

        if (is_null($user)) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether the username is blocked or has any blocked parts in it.
     * Returns true when the name is blocked or has a blocked part. Returns
     * false if ok.
     *
     * @param string $username
     *
     * @return bool
     */
    public function usernameBlocked($username)
    {
        foreach (self::$settings['username']['blocked'] as $bl) {
            if (strtolower($username) == strtolower($bl)) {
                return true;
            }
        }

        foreach (self::$settings['username']['blocked_parts'] as $bl) {
            if (strpos(strtolower($username), strtolower($bl)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether the username corresponds the desired pattern. Returns
     * true when the string matches the pattern.
     *
     * @param string $username
     *
     * @return int
     */
    public function usernameValid($username)
    {
        return preg_match(self::$settings['username']['pattern'], $username);
    }

    /**
     * Checks whether the email is already existing in the database. Returns
     * true when the email is already existing once in the database.
     *
     * @param string $email
     *
     * @return bool
     */
    public function emailExists($email)
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (is_null($user)) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether the email corresponds the desired pattern. Returns
     * true when the string matches the pattern.
     *
     * @param string $email
     *
     * @return int
     */
    public function emailValid($email)
    {
        return preg_match(self::$settings['email']['pattern'], $email);
    }

    /**
     * @param string $clientName
     * @param string $clientSecret
     * @param string $redirectUri
     * @param array  $scopes
     * @param int    $userId
     *
     * @return string
     */
    public function addApp($clientName, $clientSecret, $redirectUri, $scopes, $userId)
    {
        /** @var \App\Entity\User $user */
        $user = $this->em->find(User::class, $userId);
        $addClient = new OAuthClient();
        $addClient
            ->setClientIdentifier($clientName)
            ->setClientSecret($clientSecret)
            ->setRedirectUri($redirectUri)
            ->setScopes($scopes)
            ->setUsers($user->getId());

        $this->em->persist($addClient);
        $this->em->flush();

        return $addClient->getId();
    }

    public function addDefaultSubscriptionTypes()
    {
        $subscriptions = [];
        $subscriptions[] = (new SubscriptionType())
            ->setTitle('Basic')
            ->setPrice('0')
            ->setPermissions([]);
        $subscriptions[] = (new SubscriptionType())
            ->setTitle('Premium')
            ->setPrice('10')
            ->setPermissions(['web_service', 'support']);
        $subscriptions[] = (new SubscriptionType())
            ->setTitle('Enterprise')
            ->setPrice('30')
            ->setPermissions(['web_service', 'web_service_multiple', 'support']);

        foreach ($subscriptions as $item) {
            $this->em->persist($item);
        }
        $this->em->flush();
    }

    public function addDefaultScopes()
    {
        $scope = [];
        $scope[] = (new OAuthScope())
            ->setScope('user:id')
            ->setName('User ID')
            ->setDefault(true);
        $scope[] = (new OAuthScope())
            ->setScope('user:username')
            ->setName('Username')
            ->setDefault(false);
        $scope[] = (new OAuthScope())
            ->setScope('user:email')
            ->setName('Email address')
            ->setDefault(false);
        $scope[] = (new OAuthScope())
            ->setScope('user:name')
            ->setName('First name')
            ->setDefault(false);
        $scope[] = (new OAuthScope())
            ->setScope('user:surname')
            ->setName('Surname')
            ->setDefault(false);
        $scope[] = (new OAuthScope())
            ->setScope('user:birthday')
            ->setName('Birthday')
            ->setDefault(false);
        $scope[] = (new OAuthScope())
            ->setScope('user:subscription')
            ->setName('Subscription')
            ->setDefault(false);

        foreach ($scope as $item) {
            $this->em->persist($item);
        }
        $this->em->flush();
    }
}
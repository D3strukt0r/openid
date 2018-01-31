# Account (OrbitronDev Service)

Create ONE account where all the information is stored, only ONCE.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

What things you need to install the software and how to install them

```
Working webserver (PHP is a must)
```

### Installing

A step by step series of examples that tell you have to get a development env running

Clone the project from github

```bash
git clone https://github.com/OrbitronDev/service-account
```

Setup the project with composer

```bash
php composer.phar update
```

Next, rename `.env.dist` to `.env` and change following parameters:

```
RECAPTCHA_PUBLIC_KEY=... (ReCaptcha)
RECAPTCHA_PRIVATE_KEY=... (ReCaptcha)
SETUP_KEY=... (Updating sensitive area)
DATABASE_URL=... (Accessing databse)
MAILER_URL=... (To send emails)
```

## Built With

* [Composer](https://getcomposer.org/) - PHP Package manager
* [Bootstrap](https://getbootstrap.com/) - Template used in this service

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/OrbitronDev/service-account/tags). 

## Authors

* **Manuele Vaccari** - *Initial work* - Copied work from previous project [OrbitronDev](https://github.com/D3strukt0r/OrbitronDev)

See also the list of [contributors](https://github.com/OrbitronDev/service-account/contributors) who participated in this project.

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE.md](LICENSE.md) file for details

## Acknowledgments

* Hat tip to anyone who's code was used (Especially Stackoverflow)
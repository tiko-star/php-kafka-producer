<?php

declare(strict_types = 1);

use App\Entity\Student;
use App\Middleware\RespectValidationMiddleware;
use App\Middleware\XmlEncoderMiddleware;
use DI\ContainerBuilder;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\Setup;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Dotenv\Dotenv;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

use Respect\Validation\Validator as v;

require __DIR__.'/../vendor/autoload.php';

// Load environment variables
if (is_readable($path = __DIR__.'/../.env')) {
    (new Dotenv())->load($path);
}

// Create Container using PHP-DI
$containerBuilder = new ContainerBuilder();

$containerBuilder->useAutowiring(false);
$containerBuilder->useAnnotations(false);
$container = $containerBuilder->build();

$container->set('upload_directory', __DIR__.'/uploads');

$container->set(EntityManager::class, function () : EntityManager {
    $config = Setup::createAnnotationMetadataConfiguration(
        [__DIR__.'/../src/Entity'],
        false
    );

    $config->setMetadataDriverImpl(
        new AnnotationDriver(
            new AnnotationReader,
            [__DIR__.'/../src/Entity']
        )
    );

    $config->setMetadataCacheImpl(
        new FilesystemCache(
            __DIR__.'/../var/doctrine'
        )
    );

    return EntityManager::create(
        [
            'url' => $_ENV['DATABASE_URL'],
        ],
        $config
    );
});

$container->set(RespectValidationMiddleware::class, function () {
    return new RespectValidationMiddleware(new ResponseFactory());
});

// Set container to create App with on AppFactory
AppFactory::setContainer($container);

/**
 * Instantiate App
 *
 * In order for the factory to work you need to ensure you have installed
 * a supported PSR-7 implementation of your choice e.g.: Slim PSR-7 and a supported
 * ServerRequest creator (included with Slim PSR-7)
 */
$app = AppFactory::create();

// Add Routing Middleware
$app->addRoutingMiddleware();

// Parse json, form data and xml
$app->addBodyParsingMiddleware();

$app->add(RespectValidationMiddleware::class);

/**
 * Add Error Handling Middleware
 *
 * @param bool $displayErrorDetails -> Should be set to false in production
 * @param bool $logErrors           -> Parameter is passed to the default ErrorHandler
 * @param bool $logErrorDetails     -> Display error details in error log
 *                                  which can be replaced by a callable of your choice.
 *                                  Note: This middleware should be added last. It will not handle any exceptions/errors
 *                                  for middleware added after it.
 */
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Define app routes

$app->group('/api', function (RouteCollectorProxy $proxy) {
    $proxy->get('/students', function (Request $request, Response $response) {
        /** @var EntityManager $em */
        $em = $this->get(EntityManager::class);
        $students = $em->getRepository(Student::class)->findAll();

        return json($students, $response);
    });

    $proxy->get('/students/{id}', function (Request $request, Response $response, array $args) {
        /** @var EntityManager $em */
        $em = $this->get(EntityManager::class);
        $student = $em->getRepository(Student::class)->find($args['id']);

        if ($student === null) {
            return json(['message' => 'not found'], $response)->withStatus(404);
        }

        return json($student, $response);
    });

    $proxy->post('/students', function (Request $request, Response $response) {
        $params = (array) $request->getParsedBody();
        validate($params);

        $student = new Student();
        $student->setName($params['name']);
        $student->setEmail($params['email']);
        $student->setAge((int) $params['age']);

        /** @var EntityManager $em */
        $em = $this->get(EntityManager::class);
        $em->persist($student);
        $em->flush();

        return json($student, $response)->withStatus(201);
    });

    $proxy->patch('/students/{id}', function (Request $request, Response $response, array $args) {
        /** @var EntityManager $em */
        $em = $this->get(EntityManager::class);
        $student = $em->getRepository(Student::class)->find($args['id']);

        if ($student === null) {
            $payload = json_encode(['message' => 'not found']);

            $response->getBody()->write($payload);

            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $params = (array) $request->getParsedBody();
        validate($params);

        $student->setName($params['name']);
        $student->setEmail($params['email']);
        $student->setAge((int) $params['age']);

        $em->flush();

        return json($student, $response);
    });

    $proxy->delete('/students/{id}', function (Request $request, Response $response, array $args) {
        /** @var EntityManager $em */
        $em = $this->get(EntityManager::class);
        $student = $em->getRepository(Student::class)->find($args['id']);

        if ($student === null) {
            return json(['message' => 'not found'], $response)->withStatus(404);
        }

        $em->remove($student);
        $em->flush();

        return $response->withHeader('Content-Type', 'application/json')->withStatus(204);
    });
})->addMiddleware(new XmlEncoderMiddleware());

$app->post('/media/upload', function (Request $request, Response $response) {
    $directory = $this->get('upload_directory');
    $uploadedFiles = $request->getUploadedFiles();

    // handle single input with single file upload
    /** @var \Psr\Http\Message\UploadedFileInterface $uploadedFile */
    $uploadedFile = $uploadedFiles['my-file'];
    $data = '';

    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $uploadedFile);
        $data = ['uploaded' => $filename];
    }

    return json($data, $response);
});

/**
 * Validate given data.
 *
 * @param array $data
 */
function validate(array $data) : void
{
    $validator = new v();

    $validator->addRule(v::key('name', v::allOf(
        v::notEmpty()->setTemplate('The name must not be empty'),
        v::length(3, 24)->setTemplate('Invalid length')
    ))->setTemplate('The key \'name\' is required'));

    $validator->addRule(v::key('email', v::allOf(
        v::notEmpty()->setTemplate('The email must not be empty'),
        v::email()->setTemplate('Invalid email address')
    ))->setTemplate('The key \'email\' is required'));

    $validator->addRule(v::key('age', v::allOf(
        v::notEmpty()->setTemplate('The age must not be empty'),
        v::type('int')->setTemplate('The age must be an integer')
    ))->setTemplate('The key \'age\' is required'));

    $validator->assert($data);
}

/**
 * Return JSON response.
 *
 * @param mixed                               $data
 * @param \Psr\Http\Message\ResponseInterface $response
 *
 * @return \Psr\Http\Message\ResponseInterface
 */
function json($data, Response $response) : Response
{
    $response->getBody()->write(json_encode($data));

    return $response->withHeader('Content-Type', 'application/json');
}

/**
 * Moves the uploaded file to the upload directory and assigns it a unique name
 * to avoid overwriting an existing uploaded file.
 *
 * @param string                                  $directory    The directory to which the file is moved
 * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile The file uploaded file to move
 *
 * @return string The filename of moved file
 * @throws \Exception
 */
function moveUploadedFile(string $directory, UploadedFileInterface $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);

    // see http://php.net/manual/en/function.random-bytes.php
    $basename = bin2hex(random_bytes(8));
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory.DIRECTORY_SEPARATOR.$filename);

    return $filename;
}

// Run app
$app->run();

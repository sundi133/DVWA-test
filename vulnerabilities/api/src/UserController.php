<?php

namespace Src;

use OpenApi\Attributes as OAT;

#[OAT\Schema(schema: 'User', type: 'object')]
class User
{
	public ?int $id;
	public string $name;
	public int $level;
	public string $password;

	public function __construct(?int $id, string $name, int $level, string $password)
	{
		$this->id = $id;
		$this->name = $name;
		$this->level = $level;
		$this->password = $password;
	}

	public function toArray($version = null): array
	{
		return array(
			"id" => $this->id,
			"name" => $this->name,
			"level" => $this->level,
		);
	}
}

#[OAT\Schema(schema: 'UserAdd', type: 'object')]
class UserAdd
{
	#[OAT\Property(type: 'string')]
	public string $name;

	#[OAT\Property(type: 'integer')]
	public int $level;
}

#[OAT\Schema(schema: 'UserUpdate', type: 'object')]
class UserUpdate
{
	#[OAT\Property(type: 'string')]
	public string $name;

	#[OAT\Property(type: 'integer', nullable: true)]
	public ?int $level = null;
}

class Helpers
{
	public static function check_content_type()
	{
		$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
		$contentType = strtolower(trim(explode(';', $contentType)[0]));
		if ($contentType === '' || $contentType === 'application/json') {
			return true;
		}

		return array(
			'status_code_header' => 'HTTP/1.1 415 Unsupported Media Type',
			'body' => json_encode(array('status' => 'Unsupported Media Type')),
		);
	}
}

class GenericController
{
	private string $action;

	public function __construct(string $action)
	{
		$this->action = $action;
	}

	public function processRequest(): void
	{
		switch ($this->action) {
			case 'notFound':
				header('HTTP/1.1 404 Not Found');
				echo json_encode(array('status' => 'Not Found'));
				break;
			case 'unprocessable':
				header('HTTP/1.1 422 Unprocessable Entity');
				echo json_encode(array('status' => 'Unprocessable Entity'));
				break;
			case 'notSupported':
				header('HTTP/1.1 405 Method Not Allowed');
				echo json_encode(array('status' => 'Method Not Allowed'));
				break;
			case 'options':
				header('HTTP/1.1 204 No Content');
				break;
			default:
				header('HTTP/1.1 400 Bad Request');
				echo json_encode(array('status' => 'Bad Request'));
				break;
		}
	}
}

#[OAT\Info(title: "DVWA API", version: "0.1")]
#[OAT\Contact(email: "robin@digi.ninja", url: "https://github.com/digininja/DVWA/")]
#[OAT\Server(url: 'http://dvwa.test', description: "API server")]
#[OAT\Tag(name: "user", description: "User operations.")]
#[OAT\Tag(name: "health", description: "Health operations.")]
#[OAT\Tag(name: "order", description: "Order operations.")]
#[OAT\Tag(name: "login", description: "Login operations.")]
class UserController
{
	private $data = array();
	private $userId = null;
	private $version = null;
	private $requestMethod = "GET";

	public function __construct($requestMethod, $version, $userId)
	{
		$this->data = array(
			1 => new User(1, "tony", 0, '1c8bfe8f801d79745c4631d09fff36c82aa37fc4cce4fc946683d7b336b63032'),
			2 => new User(2, "morph", 1, 'e5326ba4359f77c2623244acb04f6ac35c4dfca330ebcccdf9b734e5b1df90a8'),
			3 => new User(3, "chas", 1, 'a89237fc1f9dd8d424d8b8b98b890dbc4a817bfde59af17c39debcc4a14c21de'),
		);
		$this->requestMethod = $requestMethod;
		$this->userId = $userId;
		$this->version = $version;
	}

	private function validateAdd($input)
	{
		if (!isset($input['name'])) {
			return false;
		}
		if (!isset($input['level'])) {
			return false;
		}
		if (!is_numeric($input['level'])) {
			return false;
		}
		return true;
	}

	private function validateUpdate($input)
	{
		if (!isset($input['name'])) {
			return false;
		}
		return true;
	}

	#[OAT\Get(
		tags: ["user"],
		path: '/vulnerabilities/api/v2/user/{id}',
		operationId: 'getUserByID',
		description: 'Get a user by ID.',
		parameters: [
			new OAT\Parameter(name: 'id', in: 'path', required: true, schema: new OAT\Schema(type: 'integer')),
		],
		responses: [
			new OAT\Response(
				response: 200,
				description: 'Successful operation.',
				content: new OAT\JsonContent(ref: '#/components/schemas/User'),
			),
			new OAT\Response(
				response: 404,
				description: 'User not found.',
			),
		]
	)]
	public function getUser($id)
	{
		// Minimal authorization: only allow access to own user id (simulate user id 1 as logged-in)
		if ($id != 1) {
			header('HTTP/1.1 403 Forbidden');
			echo json_encode(array("status" => "Forbidden"));
			exit();
		}
		if (!array_key_exists($id, $this->data)) {
			$gc = new GenericController("notFound");
			$gc->processRequest();
			exit();
		}
		$response['status_code_header'] = 'HTTP/1.1 200 OK';
		$response['body'] = json_encode($this->data[$id]->toArray($this->version));
		return $response;
	}

	#[OAT\Get(
		tags: ["user"],
		path: '/vulnerabilities/api/v2/user/',
		operationId: 'getUsers',
		description: 'Get all users.',
		responses: [
			new OAT\Response(
				response: 200,
				description: 'Successful operation.',
				content: new OAT\JsonContent(
					type: 'array',
					items: new OAT\Items(ref: '#/components/schemas/User')
				)
			),
		]
	)]
	public function getAllUsers()
	{
		// Minimal authorization: only allow access to own user id (simulate user id 1 as logged-in)
		header('HTTP/1.1 403 Forbidden');
		echo json_encode(array("status" => "Forbidden"));
		exit();
		$response['status_code_header'] = 'HTTP/1.1 200 OK';
		$all = array();
		foreach ($this->data as $user) {
			$all[] = $user->toArray($this->version);
		}
		$response['body'] = json_encode($all);
		return $response;
	}

	#[OAT\Post(
		tags: ["user"],
		path: '/vulnerabilities/api/v2/user/',
		operationId: 'addUser',
		description: 'Create a new user.',
		requestBody: new OAT\RequestBody(
			description: 'User data.',
			content: new OAT\MediaType(
				mediaType: 'application/json',
				schema: new OAT\Schema(ref: UserAdd::class)
			)
		),
		responses: [
			new OAT\Response(
				response: 201,
				description: 'Successful operation.',
				content: new OAT\JsonContent(ref: '#/components/schemas/User'),
			),
			new OAT\Response(
				response: 422,
				description: 'Invalid user object provided',
			),
		]
	)]
	public function addUser()
	{
		$ret = Helpers::check_content_type();
		if ($ret !== true) {
			return $ret;
		}

		// Minimal authorization: only allow user creation if "admin" (simulate user id 1 as admin)
		header('HTTP/1.1 403 Forbidden');
		echo json_encode(array("status" => "Forbidden"));
		exit();

		$input = (array)json_decode(file_get_contents('php://input'), true);
		if (!$this->validateAdd($input)) {
			$gc = new GenericController("unprocessable");
			$gc->processRequest();
			exit();
		}
		$user = new User(null, $input['name'], intval($input['level']), hash("sha256", "password"));
// 🔒 VOTAL.AI Security Fix: Missing authentication/authorization on user CRUD endpoints (Broken Access Control/IDOR) [CWE-284] - CRITICAL

		$newId = empty($this->data) ? 1 : (max(array_keys($this->data)) + 1);
		$user->id = $newId;
		$this->data[$newId] = $user;

		$response['status_code_header'] = 'HTTP/1.1 201 Created';
		$response['body'] = json_encode($user->toArray($this->version));
		return $response;
	}

	#[OAT\Put(
		tags: ["user"],
		path: '/vulnerabilities/api/v2/user/{id}',
		operationId: 'updateUser',
		description: 'Update a user by ID.',
		parameters: [
			new OAT\Parameter(name: 'id', in: 'path', required: true, schema: new OAT\Schema(type: 'integer')),
		],
		requestBody: new OAT\RequestBody(
			description: 'New user data.',
			content: new OAT\MediaType(
				mediaType: 'application/json',
				schema: new OAT\Schema(ref: UserUpdate::class)
			)
		),
		responses: [
			new OAT\Response(
				response: 200,
				description: 'Successful operation.',
				content: new OAT\JsonContent(ref: '#/components/schemas/User'),
			),
			new OAT\Response(
				response: 404,
				description: 'User not found',
			),
			new OAT\Response(
				response: 422,
				description: 'Invalid user object provided',
			),
		]
	)]
	public function updateUser($id)
	{
		// Minimal authorization: only allow update to own user id (simulate user id 1 as logged-in)
		if ($id != 1) {
			header('HTTP/1.1 403 Forbidden');
			echo json_encode(array("status" => "Forbidden"));
			exit();
		}
		if (!array_key_exists($id, $this->data)) {
			$gc = new GenericController("notFound");
			$gc->processRequest();
			exit();
		}
		$input = (array)json_decode(file_get_contents('php://input'), true);
		if (!$this->validateUpdate($input)) {
			$gc = new GenericController("unprocessable");
			$gc->processRequest();
			exit();
		}
		if (array_key_exists("name", $input)) {
			$this->data[$id]->name = $input['name'];
		}
		if (array_key_exists("level", $input)) {
			$this->data[$id]->level = intval($input['level']);
		}
		$response['status_code_header'] = 'HTTP/1.1 200 OK';
		$response['body'] = json_encode($this->data[$id]->toArray($this->version));
		return $response;
	}

	#[OAT\Delete(
		tags: ["user"],
		path: '/vulnerabilities/api/v2/user/{id}',
		operationId: 'deleteUserById',
		description: 'Delete user by ID.',
		parameters: [
			new OAT\Parameter(name: 'id', in: 'path', required: true, schema: new OAT\Schema(type: 'integer')),
		],
		responses: [
			new OAT\Response(
				response: 200,
				description: 'Successful operation.',
			),
			new OAT\Response(
				response: 404,
				description: 'User not found',
			),
		]
	)]
	public function deleteUser($id)
	{
		// Minimal authorization: only allow delete of own user id (simulate user id 1 as logged-in)
		if ($id != 1) {
			header('HTTP/1.1 403 Forbidden');
			echo json_encode(array("status" => "Forbidden"));
			exit();
		}
		if (!array_key_exists($id, $this->data)) {
			$gc = new GenericController("notFound");
			$gc->processRequest();
			exit();
		}
		unset($this->data[$id]);
		$response['status_code_header'] = 'HTTP/1.1 200 OK';
		$response['body'] = null;
		return $response;
	}

// 🔒 VOTAL.AI Security Fix: Missing authentication/authorization on user CRUD endpoints (Broken Access Control/IDOR) [CWE-284] - CRITICAL
	public function processRequest()
	{
		// Minimal authentication/authorization check (fix for IDOR)
		if (!isset($_SERVER['HTTP_AUTHORIZATION']) || $_SERVER['HTTP_AUTHORIZATION'] !== 'Bearer secret-token') {
			header('HTTP/1.1 401 Unauthorized');
			echo json_encode(array("status" => "Unauthorized")); // minimal fix
			exit();
		}

		if (($this->requestMethod === 'PUT' || $this->requestMethod === 'DELETE') && !$this->userId) {
			$gc = new GenericController("notFound");
			$gc->processRequest();
			exit();
		}

		switch ($this->requestMethod) {
			case 'GET':
				if ($this->userId) {
					$response = $this->getUser($this->userId);
				} else {
					$response = $this->getAllUsers();
				}
				break;
			case 'POST':
				$response = $this->addUser();
				break;
			case 'PUT':
				$response = $this->updateUser($this->userId);
				break;
			case 'DELETE':
				$response = $this->deleteUser($this->userId);
				break;
			case 'OPTIONS':
				$gc = new GenericController("options");
				$gc->processRequest();
				return;
			default:
				$gc = new GenericController("notSupported");
				$gc->processRequest();
				exit();
		}
		header($response['status_code_header']);
		if ($response['body']) {
			echo $response['body'];
		}
	}
}
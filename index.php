<?php
date_default_timezone_set('America/Sao_Paulo');
header("Content-Type: application/json");
require 'lib/vendor/autoload.php';

//require 'lib/vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
//use Gerencianet\Exception\GerencianetException;
//use Gerencianet\Gerencianet;

define('SECRET_KEY','catraca_web');
define('ALGORITHM','HS256');

$app = new \Slim\App(array('templates.path' => 'templates', 'settings' => ['displayErrorDetails' => true]));
//$app = new \Slim\App(array('templates.path' => 'templates'));

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->get('/', function(Request $request, Response $response, $args) {
	return $response->withJson(['status' => 200, 'message' => "Api Manager Catraca Web"]);
});



$app->post('/usuario/login', function(Request $request, Response $response, $args) {
	$data = $request->getParsedBody();
	//$auth = auth($request);
	/*if($auth[status] != 200){
		return $response->withJson($auth, $auth[status]);
		die;
	}*/
	
	$email = trim($data["email"]);
	$senha = trim($data["senha"]);

	if ($email == "") {
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'E-mail não informado!');
		return $response->withJson($res, $res[status]);
		die;
	} else if ($senha == "") {
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'Senha não informada!');
		return $response->withJson($res, $res[status]);
		die;
	} else {
		$res = login($email, $senha);
		return $response->withJson($res, $res[status]);
		die;
	}

});


function login($email, $senha) {

	//generateToken($sql);

	$sql = "SELECT user_id , user_email, user_nome, user_data_nasc, tipo_id, filial_id 
			FROM usuarios WHERE user_email = ? AND user_senha = SHA1(?) LIMIT 1";
	
	$stmt = getConn()->prepare($sql);
	$stmt->bindParam(1, $email , PDO::PARAM_STR);
	$stmt->bindParam(2, $senha , PDO::PARAM_STR);
	$stmt->execute();
	$countLogin = $stmt->rowCount();
	$resultUsuario = $stmt->fetchAll(PDO::FETCH_OBJ);

	if ($countLogin != 1) {
		return array('status' => 500, 'message' => "ERROR", 'result' => 'Usuário e/ou senha inválidos!');
	}

	
	$secretKey = SECRET_KEY;
	/// Here we will transform this array into JWT:
	$jwt = JWT::encode(
				$data, //Data to be encoded in the JWT
				$secretKey,
				ALGORITHM 
				); 
	$unencodedArray = ['token'=> $jwt];

	$res = array(
		'status' => 200, 
		'message' => "SUCCESS", 
		'usuario' => $resultUsuario[0], 
		'token'=> $jwt
	);
	
	return $res;
}

function auth($request) {
	$authorization = $request->getHeaderLine("Authorization");
	
	if (trim($authorization) == "") {
		return array('status' => 500, 'message' => 'Token não informado');
	} else {
		try {
			$token = JWT::decode($authorization, SECRET_KEY, array('HS256'));
			return array('status' => 200, 'token' => $token);
		} catch (Exception $e) {
			return array('status' => 401, 'message' => 'Acesso não autorizado');
		}
	}
}


function getConn() {
	
	return new PDO('mysql:host=localhost;dbname=u230569690_ser01', 'u230569690_serv', 'RO~Ng$5]jy', array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));		
	//return new PDO('mysql:host=localhost;dbname=recifesi_homemarket', 'recifesi_homemarket', 'voanubo2016', array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));		
}



function luhn_check($number) {
	
	// Strip any non-digits (useful for credit card numbers with spaces and hyphens)
	$number=preg_replace('/\D/', '', $number);

	// Set the string length and parity
	$number_length=strlen($number);
	$parity=$number_length % 2;

	// Loop through each digit and do the maths
	$total=0;
	for ($i=0; $i<$number_length; $i++) {
	$digit=$number[$i];
	// Multiply alternate digits by two
	if ($i % 2 == $parity) {
		$digit*=2;
		// If the sum is two digits, add them together (in effect)
		if ($digit > 9) {
		$digit-=9;
		}
	}
	// Total up the digits
	$total+=$digit;
	}

	// If the total mod 10 equals 0, the number is valid
	if ($total % 10 == 0) {
		return  true;
	} else {
		return false;
	} 
}

function sendMail($html, $email, $nome) {
	$mail = new PHPMailer();
	$mail->IsSMTP(); // Define que a mensagem será SMTP
	$mail->Host = "smtp.gmail.com"; // Endereço do servidor SMTP
	$mail->SMTPAuth = true; // Usa autenticação SMTP? (opcional)
	$mail->Username = 'rafaelfreitas.servtec@gmail.com'; // Usuário do servidor SMTP
	$mail->Password = 'recifesites2017'; // Senha do servidor SMTP
	$mail->From = "rafaelfreitas.servtec@gmail.com"; // Seu e-mail
	$mail->FromName = "Catraca Web APP"; // Seu nome
	$mail->IsHTML(false); // Define que o e-mail será enviado como HTML	
	$mail->AddAddress($email, $nome);
	$mail->Subject  = "Catraca Web APP"; // Assunto da mensagem
	$mail->Body = $html;
	$mail->Send();
}


$app->run();
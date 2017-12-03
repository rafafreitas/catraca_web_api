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
	
	$email = trim($data["user_email"]);
	$senha = trim($data["user_senha"]);

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

	//Pegar dados do Usuário
	$sql = "SELECT user_id ,user_nome, user_email, user_senha, user_cpf, DATE_FORMAT(user_data_nasc, '%d/%m/%Y') as dateFormat, 
			user_data_nasc, tipo_id, filial_id 
			FROM usuarios WHERE user_email = ? AND user_senha = ? LIMIT 1";
	
	$stmt = getConn()->prepare($sql);
	$stmt->bindParam(1, $email , PDO::PARAM_STR);
	$stmt->bindParam(2, $senha , PDO::PARAM_STR);
	$stmt->execute();
	$countLogin = $stmt->rowCount();
	$resultUsuario = $stmt->fetchAll(PDO::FETCH_OBJ);

	if ($countLogin != 1) {
		return array('status' => 500, 'message' => "ERROR", 'result' => 'Usuário e/ou senha inválidos!');
	}

	$resultUsuario[0]->user_cpf = mask($resultUsuario[0]->user_cpf,'###.###.###-##');

	//Pegar dados dos Responsáveis
	$sql = "SELECT resp_id ,resp_nome FROM responsaveis 
			WHERE filial_id = ?";
	
	$stmt = getConn()->prepare($sql);
	$stmt->bindParam(1, $resultUsuario[0]->filial_id , PDO::PARAM_STR);
	$stmt->execute();
	$resultResponsaveis = $stmt->fetchAll(PDO::FETCH_OBJ);

	//Pegar Motivos das Visitas
	$sql = "SELECT visita_motivo_id ,visita_motivo_desc FROM visita_motivo 
			WHERE filial_id = ?";
	
	$stmt = getConn()->prepare($sql);
	$stmt->bindParam(1, $resultUsuario[0]->filial_id , PDO::PARAM_STR);
	$stmt->execute();
	$resultMotivos = $stmt->fetchAll(PDO::FETCH_OBJ);
	

	//Pegar Informações Gerais
	//Quantidade de Visitantes
	$sql = "select count(*) as quantidade 
			from visitante_visita vivi 
			inner join visitas vis 
			on vivi.visita_id = vis.visita_id
			where vis.filial_id = ? 
			AND vivi.visitante_visita_saida is null";
	
	$stmt = getConn()->prepare($sql);
	$stmt->bindParam(1, $resultUsuario[0]->filial_id , PDO::PARAM_STR);
	$stmt->execute();
	$visitantes_empresa = $stmt->fetchAll(PDO::FETCH_OBJ);


	//Quantidade de Veículos
	$sql = "select count(*) as quantidade 
			from carros_visita cavi 
			inner join visitas vis 
			on cavi.visita_id = vis.visita_id
			where vis.filial_id = ? 
			AND cavi.carro_visita_saida is null";
	
	$stmt = getConn()->prepare($sql);
	$stmt->bindParam(1, $resultUsuario[0]->filial_id , PDO::PARAM_STR);
	$stmt->execute();
	$veiculos_empresa = $stmt->fetchAll(PDO::FETCH_OBJ);

	
	$latitude  = "-8.1515521";
	$longitude = "-34.9199225";

	$uteis = array(
		'qtd_visitantes' => $visitantes_empresa[0]->quantidade,
		'qtd_veiculos'   => $veiculos_empresa[0]->quantidade,
		'latitude'       => $latitude,
		'longitude'      => $longitude,
	);


	//Gerar TOKEN
	$tokenId    = base64_encode(mcrypt_create_iv(32));
	$issuedAt   = time();
	$notBefore  = $issuedAt + 10;  //Adding 10 seconds
	$expire     = $notBefore + 1972000000; // Adding 60 seconds
	$serverName = 'http://catracaweb.com.br/'; /// set your domain name 

	
	$data = [
		'iat'  => $issuedAt,         // Issued at: time when the token was generated
		'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
		'iss'  => $serverName,       // Issuer
		'nbf'  => $notBefore,        // Not before
		'exp'  => $expire,           // Expire
		'data' => $resultUsuario[0] //[                  // Data related to the logged user you can set your required data
			//'apt'   => $apt, // id from the users table
			 //'condominio' => $id_condominio, //  name
			
				 // ]
	];
	$secretKey = SECRET_KEY;
	/// Here we will transform this array into JWT:
	$jwt = JWT::encode(
				$data, //Data to be encoded in the JWT
				$secretKey,
				ALGORITHM 
				); 
	$unencodedArray = ['token'=> $jwt];

	$res = array(
		'status' 		=> 200, 
		'message' 		=> "SUCCESS", 
		'usuario' 		=> $resultUsuario[0], 
		'responsaveis' 	=> $resultResponsaveis, 
		'motivos'	 	=> $resultMotivos, 
		'uteis' 		=> $uteis, 
		'token'			=> $jwt
	);
	
	return $res;
}


$app->post('/usuario/alterar', function(Request $request, Response $response, $args) {
	$data = $request->getParsedBody();
	$auth = auth($request);
	if($auth[status] != 200){
		return $response->withJson($auth, $auth[status]);
		die;
	}
	
	// var_dump($auth["token"]->data->user_senha);
	// die;

	$id = trim($data["user_id"]);
	$nome = trim($data["user_nome"]);
	$email = trim($data["user_email"]);
	$cpf = trim($data["user_cpf"]);
	$dataNascimento = trim($data["user_data_nasc"]);

	$cpf = str_replace('-', '' , $cpf);
	$cpf = str_replace('.', '' , $cpf);

	$data = explode("/", $dataNascimento);
	$dataNascimento = $data[2]."-".$data[1]."-".$data[0];
	

	if ($nome == "") {
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'Nome não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}
	
	if ($cpf == "") {
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'CPF não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}

	if ($email == "") {
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'E-mail não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}

	if ($dataNascimento == "") {
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'Data de nascimento não informada!');
		return $response->withJson($res, $res[status]);
		die;	
	}

	$sql = "UPDATE 
			usuarios
			SET  user_nome = ?,
				 user_email = ?,
				 user_cpf = ?,
				 user_data_nasc = ?
			WHERE user_id = ?";

	$stmt = getConn()->prepare($sql);

	$stmt->bindParam(1, $nome , PDO::PARAM_STR);
	$stmt->bindParam(2, $email , PDO::PARAM_STR);
	$stmt->bindParam(3, $cpf , PDO::PARAM_STR);
	$stmt->bindParam(4, $dataNascimento , PDO::PARAM_STR);
	$stmt->bindParam(5, $id , PDO::PARAM_INT);

	if ($stmt->execute()) {
		$senha = $auth["token"]->data->user_senha;
		$res = login($email, $senha);
		return $response->withJson($res, $res[status]);
		die;		
	} else {
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'Não foi possível atualizar seus dados, tente novamente por favor!');
		return $response->withJson($res, $res[status]);
		die;
	}

});

$app->post('/usuario/recupera_senha', function(Request $request, Response $response, $args) {
	$data = $request->getParsedBody();
	$auth = auth($request);

	$email = trim($data["email"]);
	$data = trim($data["data_nascimento"]);

	if ($email == "") {
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'E-mail não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}

	if ($data == "") {
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'Data de Nascimeto não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}

	$sql = "SELECT * FROM usuario WHERE email_usu = '".$email."'";
	
	$stmt = getConn()->prepare($sql);
	$stmt->execute();

	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$html = '<img src=""/>
			<h3>Solicita&ccedil;&atilde;o de Senha</h3>
			<p>
			Sua senha é: <b>'.$row["senha_usu"].'</b>
			</p>';

		sendMail($html, $row["email_usu"], $row["nome_usu"]);
	}

	$res = array('status' => 200, 'message' => "SUCCESS", 'result' => "Senha enviada para o e-mail informado!");
	return $response->withJson($res, $res[status]);
	die;

});


function auth($request) {
	$authorization = $request->getHeaderLine("Authorization");
	
	if (trim($authorization) == "") {
		return array('status' => 500, 'message' => 'ERROR', 'result' => 'Token não informado');
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

function mask($val, $mask){
	$maskared = '';
	$k = 0;
	for($i = 0; $i<=strlen($mask)-1; $i++) {
	  if($mask[$i] == '#') {
	    if(isset($val[$k])) {
	    $maskared .= $val[$k++];
	    }
	  } else{
	    if(isset($mask[$i])) {
	      $maskared .= $mask[$i];
	    }
	  }
	}
	return $maskared;
}


$app->run();
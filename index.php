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


$app->post('/veiculo/consulta', function(Request $request, Response $response, $args) {
	$data = $request->getParsedBody();
	$auth = auth($request);
	if($auth[status] != 200){
		return $response->withJson($auth, $auth[status]);
		die;
	}

	$placa = trim($data["veic_placa"]);

	if ($placa == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'placa não informada!');
		return $response->withJson($res, $res[status]);
		die;	
	}

	$placa = str_replace('-', '' , $placa);

	$sql = "SELECT * FROM veiculos WHERE veic_placa = ? LIMIT 1;";
	$stmt = getConn()->prepare($sql);
	$stmt->bindParam(1, $placa , PDO::PARAM_STR);
	$stmt->execute();
	$count = $stmt->rowCount();
	$resultVeiculo = $stmt->fetchAll(PDO::FETCH_OBJ);
	

	if ($count == 1) {
		$email = $auth["token"]->data->user_email;
		$senha = $auth["token"]->data->user_senha;
		$newData = login($email, $senha);

		if ($newData['status'] == 200 && $newData['message'] == 'SUCCESS') {
			unset($newData['status']);
			unset($newData['message']);
			$res = array(
				'status' => 200, 
				'message' => "SUCCESS", 
				'veiculo' => $resultVeiculo[0],
				'gerais' => $newData
			);
			return $response->withJson($res, $res[status]);
			die;		
		}else{

			$res = array('status' => 401, 'message' => 'Acesso não autorizado');
			return $response->withJson($res, $res[status]);
			die;

		}


	} elseif ($count == 0) {
		# code...
		$res = array('status' => 204, 'message' => "NOTFOUND", 'result' => 'Não foi encontrado veículo com esta placa!');
		return $response->withJson($res, 200);
		die;
	}

});


$app->post('/veiculo/cadastro', function(Request $request, Response $response, $args) {
	$data = $request->getParsedBody();
	$auth = auth($request);
	if($auth[status] != 200){
		return $response->withJson($auth, $auth[status]);
		die;
	}
	$id = trim($auth["token"]->data->user_id);
	$placa = trim($data["veic_placa"]);
	$modelo = trim($data["veic_modelo"]);
	$foto = trim($data["veic_foto"]);

	$placa = str_replace('-', '' , $placa);

	if ($placa == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'Placa não informada!');
		return $response->withJson($res, $res[status]);
		die;	
	}
	if ($modelo == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'Modelo não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}
	if ($foto == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'Foto não informada!');
		return $response->withJson($res, $res[status]);
		die;	
	}

	if (isset($data["veic_id"]) && !empty($data["veic_id"])) {

		$sql = "UPDATE veiculos
			SET  veic_placa = ?,
				 veic_modelo = ?,
				 veic_foto = ?,
				 user_id = ?
			WHERE veic_id = ?";
		$stmt = getConn()->prepare($sql);
		$stmt->bindParam(1, $placa , PDO::PARAM_STR);
		$stmt->bindParam(2, $modelo , PDO::PARAM_STR);
		$stmt->bindParam(3, $foto , PDO::PARAM_STR);
		$stmt->bindParam(4, $id , PDO::PARAM_STR);
		$stmt->bindParam(5, $data["veic_id"] , PDO::PARAM_STR);
		$stmt->execute();
		$count = $stmt->rowCount();
		$msg = "Dados atualizados com sucesso!";

	}else{

		$sql = "INSERT INTO veiculos (veic_placa, veic_modelo, veic_foto, user_id)
				VALUES (?, ?, ?, ?)";
		$stmt = getConn()->prepare($sql);
		$stmt->bindParam(1, $placa , PDO::PARAM_STR);
		$stmt->bindParam(2, $modelo , PDO::PARAM_STR);
		$stmt->bindParam(3, $foto , PDO::PARAM_STR);
		$stmt->bindParam(4, $id , PDO::PARAM_STR);
		$stmt->execute();
		$count = $stmt->rowCount();
		$msg = "Dados cadastrados com sucesso!";


	}

	if ($count == 1) {
		$email = $auth["token"]->data->user_email;
		$senha = $auth["token"]->data->user_senha;
		$newData = login($email, $senha);

		if ($newData['status'] == 200 && $newData['message'] == 'SUCCESS') {
			unset($newData['status']);
			unset($newData['message']);
			$res = array(
				'status' => 200, 
				'message' => "SUCCESS", 
				'result' => $msg,
				'gerais' => $newData
			);
			return $response->withJson($res, $res[status]);
			die;		
		}else{

			$res = array('status' => 401, 'message' => 'Acesso não autorizado');
			return $response->withJson($res, $res[status]);
			die;

		}


	} elseif ($count == 0) {
		# code...
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'Não foi possível inserir o veículo, tente novamente!');
		return $response->withJson($res, 200);
		die;
	}

});


$app->post('/visitante/consulta', function(Request $request, Response $response, $args) {
	$data = $request->getParsedBody();
	$auth = auth($request);
	if($auth[status] != 200){
		return $response->withJson($auth, $auth[status]);
		die;
	}

	$cpf = trim($data["visitante_cpf"]);

	if ($cpf == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'CPF não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}

	$cpf = str_replace('-', '' , $cpf);
	$cpf = str_replace('.', '' , $cpf);

	$sql = "SELECT * FROM visitantes WHERE visitante_cpf = ? LIMIT 1;";
	$stmt = getConn()->prepare($sql);
	$stmt->bindParam(1, $cpf , PDO::PARAM_STR);
	$stmt->execute();
	$count = $stmt->rowCount();
	$resultVisitante = $stmt->fetchAll(PDO::FETCH_OBJ);
	

	if ($count == 1) {
		$email = $auth["token"]->data->user_email;
		$senha = $auth["token"]->data->user_senha;
		$newData = login($email, $senha);

		if ($newData['status'] == 200 && $newData['message'] == 'SUCCESS') {
			unset($newData['status']);
			unset($newData['message']);
			$res = array(
				'status' => 200, 
				'message' => "SUCCESS", 
				'veiculo' => $resultVisitante[0],
				'gerais' => $newData
			);
			return $response->withJson($res, $res[status]);
			die;		
		}else{

			$res = array('status' => 401, 'message' => 'Acesso não autorizado');
			return $response->withJson($res, $res[status]);
			die;

		}


	} elseif ($count == 0) {
		# code...
		$res = array('status' => 204, 'message' => "NOTFOUND", 'result' => 'Não foi encontrado um visitante com este CPF!');
		return $response->withJson($res, 200);
		die;
	}

});


$app->post('/visitante/cadastro', function(Request $request, Response $response, $args) {
	$data = $request->getParsedBody();
	$auth = auth($request);
	if($auth[status] != 200){
		return $response->withJson($auth, $auth[status]);
		die;
	}

	$nome = trim($data["visitante_nome"]);
	$cpf = trim($data["visitante_cpf"]);
	$rg = trim($data["visitante_rg"]);
	$data = trim($data["visitante_data_nasc"]);
	$foto_face = trim($data["visitante_foto_face"]);
	$foto_doc = trim($data["visitante_foto_doc"]);
	$user_id = trim($auth["token"]->data->user_id);


	$placa = str_replace('-', '' , $placa);

	if ($nome == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'Nome não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}
	if ($cpf == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'CPF não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}
	if (!validaCPF($cpf)) {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'CPF inválido!');
		return $response->withJson($res, $res[status]);
		die;	
	}
	if ($rg == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'RG não informado!');
		return $response->withJson($res, $res[status]);
		die;	
	}
	if ($data == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'Foto não informada!');
		return $response->withJson($res, $res[status]);
		die;	
	}
	if ($foto_face == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'Foto do visitante não informada!');
		return $response->withJson($res, $res[status]);
		die;	
	}
	if ($foto_doc == "") {
		$res = array('status' => 400, 'message' => "INVALID", 'result' => 'Foto do Documento não informada!');
		return $response->withJson($res, $res[status]);
		die;	
	}

	$data = explode("/", $data);
	$dataNascimento = $data[2]."-".$data[1]."-".$data[0];

	$sql = "INSERT INTO visitantes (visitante_nome, visitante_cpf, visitante_rg, visitante_data_nasc, 
									visitante_foto_face, visitante_foto_doc, user_id)
			VALUES (?, ?, ?, ?, ?, ?, ?)";
	$stmt = getConn()->prepare($sql);
	$stmt->bindParam(1, $nome , PDO::PARAM_STR);
	$stmt->bindParam(2, $cpf , PDO::PARAM_STR);
	$stmt->bindParam(3, $rg , PDO::PARAM_STR);
	$stmt->bindParam(4, $dataNascimento , PDO::PARAM_STR);
	$stmt->bindParam(5, $foto_face , PDO::PARAM_STR);
	$stmt->bindParam(6, $foto_doc , PDO::PARAM_STR);
	$stmt->bindParam(7, $user_id , PDO::PARAM_STR);
	$stmt->execute();
	$count = $stmt->rowCount();
	$resultVeiculo = $stmt->fetchAll(PDO::FETCH_OBJ);
	

	if ($count == 1) {
		$email = $auth["token"]->data->user_email;
		$senha = $auth["token"]->data->user_senha;
		$newData = login($email, $senha);

		if ($newData['status'] == 200 && $newData['message'] == 'SUCCESS') {
			unset($newData['status']);
			unset($newData['message']);
			$res = array(
				'status' => 200, 
				'message' => "SUCCESS", 
				'result' => 'Dados cadastrados com sucesso!',
				'gerais' => $newData
			);
			return $response->withJson($res, $res[status]);
			die;		
		}else{

			$res = array('status' => 401, 'message' => 'Acesso não autorizado');
			return $response->withJson($res, $res[status]);
			die;

		}

	} elseif ($count == 0) {
		# code...
		$res = array('status' => 500, 'message' => "ERROR", 'result' => 'Não foi possível inserir o visitante, tente novamente!');
		return $response->withJson($res, 200);
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

function validaCPF($cpf = null) {
    
       // Verifica se um número foi informado
       if(empty($cpf)) {
           return false;
       }
    
       // Elimina possivel mascara
       $cpf = ereg_replace('[^0-9]', '', $cpf);
       $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);
        
       // Verifica se o numero de digitos informados é igual a 11 
       if (strlen($cpf) != 11) {
           return false;
       }
       // Verifica se nenhuma das sequências invalidas abaixo 
       // foi digitada. Caso afirmativo, retorna falso
       else if ($cpf == '00000000000' || 
           $cpf == '11111111111' || 
           $cpf == '22222222222' || 
           $cpf == '33333333333' || 
           $cpf == '44444444444' || 
           $cpf == '55555555555' || 
           $cpf == '66666666666' || 
           $cpf == '77777777777' || 
           $cpf == '88888888888' || 
           $cpf == '99999999999') {
           return false;
        // Calcula os digitos verificadores para verificar se o
        // CPF é válido
        } else {   
            
           for ($t = 9; $t < 11; $t++) {
                
               for ($d = 0, $c = 0; $c < $t; $c++) {
                   $d += $cpf{$c} * (($t + 1) - $c);
               }
               $d = ((10 * $d) % 11) % 10;
               if ($cpf{$c} != $d) {
                   return false;
               }
           }
    
           return true;
       }
}


$app->run();
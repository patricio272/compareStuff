<?php
require_once('functions.php');


if(isset($_REQUEST["action"])){
	$action = $_REQUEST["action"];
	if($action == 'login'){
		$username = $_REQUEST["username"];
		$password = $_REQUEST["password"];
		mysql_select_db("Satisface",$conn);
		mysql_set_charset('utf8',$conn);

		$sql = "select * from USUARIOS where username = '".$username."' and clave = '".$password."'";
		$query = mysql_query($sql);
		$cantResults = mysql_num_rows($query);
		if($cantResults == 0){
			$data = array();
			$data["err"] = "Usuario y/o Contraseña Incorrectos";
			echo json_encode($data);
		}
		else{
			// Save info in session
			$results = mysql_fetch_array($query, MYSQL_ASSOC);

			// Session Data to Save later on if everything goes OK!!!
			$id_usuario = $results["id_usuario"];
			$username = $results["username"];
			$nombre = $results["nombre"];

			// Get COMERCIOS (COMERCIO_PREDICTIVO) asociados al usuario
			$sql = "select COMERCIO_PREDICTIVO.* from COMERCIO_PREDICTIVO, COMERCIO_USUARIO, USUARIOS where COMERCIO_PREDICTIVO.id_comercio = COMERCIO_USUARIO.id_comercio and COMERCIO_USUARIO.id_usuario = USUARIOS.id_usuario and COMERCIO_PREDICTIVO.codigoSap != '' and USUARIOS.id_usuario = '".$id_usuario."'";
			$query = mysql_query($sql);
			$cantResults = mysql_num_rows($query);
			if($cantResults == 0){
				$data = array();
				$data["err"] = "Usuario sin comercios asociados.";
				echo json_encode($data);
			}
			else{
				$comerciosArr = array();
				while($results = mysql_fetch_array($query, MYSQL_ASSOC)){
					$id_comercio = $results["id_comercio"];
					$concesionario = $results["concesionario"];
					$codigoSap = $results["codigoSap"];

					$tempArr = array();
					$tempArr["id_comercio"] = $id_comercio;
					$tempArr["concesionario"] = $concesionario;
					$tempArr["codigoSap"] = $codigoSap;

					array_push($comerciosArr, $tempArr);
				}

				// Save in Session
				$_SESSION["id_usuario"] = $id_usuario;
				$_SESSION["username"] = $username;
				$_SESSION["nombre"] = $nombre;

				echo json_encode($comerciosArr);
			}
		}
	}
	else if($action == 'login_comercio'){
		// Save in Session
		$_SESSION["id_comercio"] = $_REQUEST["id_comercio"];
		$_SESSION["concesionario"] = $_REQUEST["concesionario"];
		$_SESSION["codigoSap"] = $_REQUEST["codigoSap"];

		$_SESSION["user_logged"] = 'logged';
		echo "OK";
	}
	else if($action == 'logout'){
		$from = $_REQUEST["from"];
		if($from == 'plataforma'){
			unset($_SESSION["id_usuario"]);
			unset($_SESSION["username"]);
			unset($_SESSION["nombre"]);
			unset($_SESSION["id_comercio"]);
			unset($_SESSION["concesionario"]);
			unset($_SESSION["codigoSap"]);
			unset($_SESSION["user_logged"]);
		}
		else if($from == 'ingresocav'){
			unset($_SESSION["cav_id_usuario"]);
			unset($_SESSION["cav_username"]);
			unset($_SESSION["cav_nombre"]);
			unset($_SESSION["cav_user_logged"]);
		}
		echo "OK";
	}
	else if($action == 'cavLogin'){
		$username = $_REQUEST["username"];
		$password = $_REQUEST["password"];
		$password = md5($password);
		mysql_select_db("TOYOTA_PLATFORM",$conn);
		mysql_set_charset('utf8',$conn);

		$sql = "select id, username, nombre, activo from USUARIO_CAV where username = BINARY '".$username."' and password = '".$password."'";
		$query = mysql_query($sql);
		$cantResults = mysql_num_rows($query);
		if($cantResults == 0){
			$data = array();
			$data["err"] = "Usuario y/o Contraseña Incorrectos";
			echo json_encode($data);
		}
		else{
			$results = mysql_fetch_array($query, MYSQL_ASSOC);
			if($results["activo"] == '0'){
				$data = array();
				$data["warn"] = "Usuario Inactivo";
				echo json_encode($data);
			}
			else{
				// Session Data to Save later on if everything goes OK!!!
				$cav_id_usuario = $results["id"];
				$cav_username = $results["username"];
				$cav_nombre = $results["nombre"];

				// Save in Session
				$_SESSION["cav_id_usuario"] = $cav_id_usuario;
				$_SESSION["cav_username"] = $cav_username;
				$_SESSION["cav_nombre"] = $cav_nombre;

				$_SESSION["cav_user_logged"] = 'logged';

				$ok = array();
				$ok["OK"] = 'OK';
				echo json_encode($ok);
			}
		}
	}
	else if($action == 'getWSInfo'){
		mysql_select_db("TOYOTA_PLATFORM",$conn);
		mysql_set_charset('utf8',$conn);
		require_once("WSFactDealer.php");

		// Format codigoSap con 10 digitos y rellenar hacia la izquierda con CEROS!!
		$codigoSapTemp = $_SESSION["codigoSap"];
		$codigoSapArr = array();
		$codigoSapArr = str_split($codigoSapTemp);
		$cerosArr = array();
		$cantLimite = 10 - count($codigoSapArr);

		for($i=0;$i<$cantLimite;$i++){
			array_push($cerosArr, '0');
		}
		for($i=0;$i<count($codigoSapArr);$i++){
			array_push($cerosArr, $codigoSapArr[$i]);
		}
		$codigoSap = '';
		for($i=0;$i<count($cerosArr);$i++){
			$codigoSap = $codigoSap . $cerosArr[$i];
		}

		// Ask MySQL to get 60 days window
		$sql = "select date_add(curdate(), INTERVAL -60 DAY) as inicio, curdate() as fin";
		$query = mysql_query($sql);
		$results = mysql_fetch_array($query, MYSQL_ASSOC);
		$fechaInicio = $results["inicio"];
		$fechaFin = $results["fin"];

		$fechaInicioArr = explode('-', $fechaInicio);
		$fechaFinArr = explode('-', $fechaFin);

		$fechaInicio = $fechaInicioArr[2] . '-' . $fechaInicioArr[1] . '-' . $fechaInicioArr[0];
		$fechaFin = $fechaFinArr[2] . '-' . $fechaFinArr[1] . '-' . $fechaFinArr[0];

		$toyotaService = new WSFactDealer();

		$request = new GetFactDealer();
		$request->Usuario = "toyotaws";
		$request->Clave = "toyota@2015";
		$request->CodigoConcesionario = $codigoSap;
		$request->FechaInicio = $fechaInicio;
		$request->FechaFin = $fechaFin;

		$salida = $toyotaService->GetFactDealer($request)->GetFactDealerResult;
		$xmlStr = $salida->any;

		echo $xmlStr;
	}
	else if($action == 'getComerciosAsociados'){
		mysql_select_db("Satisface",$conn);
		mysql_set_charset('utf8',$conn);
		// Get COMERCIOS (COMERCIO_PREDICTIVO) asociados al usuario
		$sql = "select COMERCIO_PREDICTIVO.* from COMERCIO_PREDICTIVO, COMERCIO_USUARIO, USUARIOS where COMERCIO_PREDICTIVO.id_comercio = COMERCIO_USUARIO.id_comercio and COMERCIO_USUARIO.id_usuario = USUARIOS.id_usuario and COMERCIO_PREDICTIVO.codigoSap != '' and USUARIOS.id_usuario = '".$_SESSION["id_usuario"]."'";
		$query = mysql_query($sql);
		$cantResults = mysql_num_rows($query);
		if($cantResults == 0){
			$data = array();
			$data["err"] = "Usuario sin comercios asociados.";
			echo json_encode($data);
		}
		else{
			$comerciosArr = array();
			while($results = mysql_fetch_array($query, MYSQL_ASSOC)){
				$id_comercio = $results["id_comercio"];
				$concesionario = $results["concesionario"];
				$codigoSap = $results["codigoSap"];

				$tempArr = array();
				$tempArr["id_comercio"] = $id_comercio;
				$tempArr["concesionario"] = $concesionario;
				$tempArr["codigoSap"] = $codigoSap;

				array_push($comerciosArr, $tempArr);
			}

			$info = array();
			$info["comerciosArr"] = $comerciosArr;
			$info["id_comercio"] = $_SESSION["id_comercio"];
			$info["concesionario"] = $_SESSION["concesionario"];
			$info["codigoSap"] = $_SESSION["codigoSap"];
			echo json_encode($info);
		}
	}
	else if($action == 'changeComercio'){
		$_SESSION["id_comercio"] = $_REQUEST["id_comercio"];
		$_SESSION["concesionario"] = $_REQUEST["concesionario"];
		$_SESSION["codigoSap"] = $_REQUEST["codigoSap"];

		echo "OK";
	}
	else if($action == 'cleanInfoFromWS'){
		// Eliminar de wsArr aquellas filas que existen en DB, pues
		// si existen en DB ==> Aquellas que ya se vendieron
		$wsArr = $_REQUEST["wsArr"];
		$wsArr = json_decode($wsArr);

		mysql_select_db("TOYOTA_PLATFORM", $conn);
		mysql_set_charset('utf8',$conn);

		$sql = "select * from datos";
		$query = mysql_query($sql);
		$cantResults = mysql_num_rows($query);
		if($cantResults == 0){
			echo json_encode($wsArr);
		}
		else{
			$newWSArr = array();

			for($i=0;$i<count($wsArr);$i++){
				$wsArr[$i]->borrar = "0";
			}

			while($results = mysql_fetch_array($query, MYSQL_ASSOC)){
				$stock = $results["stock"];
				for($i=0;$i<count($wsArr);$i++){
					if($stock == $wsArr[$i]->stock){
						$wsArr[$i]->borrar = "1";
					}
				}
			}

			for($i=0;$i<count($wsArr);$i++){
				if($wsArr[$i]->borrar == "0"){
					$tempArr = array();
					$tempArr["stock"] = $wsArr[$i]->stock;
					$tempArr["vin"] = $wsArr[$i]->vin;
					$tempArr["numMotor"] = $wsArr[$i]->numMotor;
					$tempArr["modelo"] = $wsArr[$i]->modelo;
					$tempArr["color"] = $wsArr[$i]->color;
					$tempArr["borrar"] = $wsArr[$i]->borrar;

					array_push($newWSArr, $tempArr);
				}
			}

			echo json_encode($newWSArr);
		}
	}
	else if($action == 'getFacturas'){
		// Datos de WS de Toyota, que coinciden con los de DB que no tienen patente seteada aun
		$wsArr = $_REQUEST["wsArr"];
		$wsArr = json_decode($wsArr);

		mysql_select_db("TOYOTA_PLATFORM",$conn);
		mysql_set_charset('utf8',$conn);

		$stockArrStr = '';
		for($i=0;$i<count($wsArr)-1;$i++){
			$stock = $wsArr[$i]->stock;
			$stockArrStr = $stockArrStr . "'" . $stock . "', ";
		}
		$stockArrStr = $stockArrStr . "'" . $wsArr[count($wsArr)-1]->stock . "'";

		$sql = "select STATUS_VEHICULO.id as status_id, STATUS_VEHICULO.nombre as nombreStatus, datos.*, datos.id as datos_id from datos, STATUS_VEHICULO where datos.status = STATUS_VEHICULO.id and datos.patente = '' and datos.stock IN (".$stockArrStr.")";
		$query = mysql_query($sql);
		$info = array();
		while($results = mysql_fetch_array($query, MYSQL_ASSOC)){
			array_push($info, $results);
		}

		echo json_encode($info);
		
	}
	else if($action == 'savePatente'){
		$patente = $_REQUEST["patente"];
		$datos_id = $_REQUEST["datos_id"];
		$vin = $_REQUEST["vin"];
		mysql_select_db("TOYOTA_PLATFORM",$conn);
		mysql_set_charset('utf8',$conn);

		// Update patente en datos
		$sql = "update datos set patente = '".$patente."' where datos.id = '".$datos_id."'";
		$query = mysql_query($sql);

		// Insert Log for HISTORIAL_INGRESO_PATENTE
		$sql = "insert into HISTORIAL_INGRESO_PATENTE(datos_id, vin, patente, fecha, Satisface_id_usuario) values('".$datos_id."', '".$vin."', '".$patente."', NOW(), '".$$_SESSION["id_usuario"]."')";
		$query = mysql_query($sql);

		echo "OK";
	}
	else if($action == 'anularVenta'){
		$datos_id = $_REQUEST["datos_id"];
		$vin = $_REQUEST["vin"];
		mysql_select_db("TOYOTA_PLATFORM",$conn);
		mysql_set_charset('utf8',$conn);

		$DeletionOK = true;
		$InsertionOK = true;

		$sql = "select documento from datos where id = '".$datos_id."' and vin = '".$vin."'";
		$query = mysql_query($sql);
		$results = mysql_fetch_array($query, MYSQL_ASSOC);
		$documento = $results["documento"];

		// Eliminar fila en datos
		$sql = "delete from datos where id = '".$datos_id."' and vin = '".$vin."'";
		$DeletionOK = mysql_query($sql);

		// Insert en HISTORIAL_ANULACION_VENTA
		$sql = "insert into HISTORIAL_ANULACION_VENTA(datos_id, vin, fecha, documento, Satisface_id_usuario) values('".$datos_id."', '".$vin."', NOW(), '".$documento."', '".$_SESSION["id_usuario"]."')";
		$InsertionOK = mysql_query($sql);

		$res = [];
		$err = '';
		if(!$DeletionOK || !$InsertionOK){
			if(!$DeletionOK){
				$err = $err . 'DB DELETE Error; ';
			}
			if(!$InsertionOK){
				$err = $err . 'DB INSERT Error; ';
			}
		}

		if($err != ''){
			$res["err"] = $err;
		}
		else{
			$res["OK"] = 'OK';
		}

		echo json_encode($res);
	}
	else if($action == 'getPatentes'){
		mysql_select_db("TOYOTA_PLATFORM",$conn);
		mysql_set_charset('utf8',$conn);

		$info = array();

		$sql = "select datos.id as datos_id, patente from datos where CAV_id = '0' and patente != ''";
		$query = mysql_query($sql);
		while($results = mysql_fetch_array($query, MYSQL_ASSOC)){
			array_push($info, $results);
		}

		echo json_encode($info);
	}
	else if($action == 'run'){
		// Test to keep process in background after sending response to client.
		mysql_select_db("TOYOTA_PLATFORM",$conn);
		mysql_set_charset('utf8',$conn);
		$info = array();
		$info["id"] = '1';
		$info["name"] = 'Patricio Toledo';

		// Send but keep processing
		ignore_user_abort(true);
		set_time_limit(0);

		ob_start();
		// do initial processing here

		echo json_encode($info); // send the response


		header('Connection: close');
		header('Content-Length: '.ob_get_length());
		ob_end_flush();
		ob_flush();
		flush();

		// now the request is sent to the browser, but the script is still running
		// so, you can continue...
		for($i=1;$i<=20000;$i++){
			$sql = "insert into xssffHfwfwIfwfSfwTORIAL_INGRESO_PATENTE(datos_id) values('1')";
			$query = mysql_query($sql);
		}
	}
	else if($action == 'uploadFactura'){
		// Row Information
		$stock = $_REQUEST["stock"];
		$vin = $_REQUEST["vin"];
		$numMotor = $_REQUEST["numMotor"];
		$modelo = $_REQUEST["modelo"];
		$color = $_REQUEST["color"];

		if(!empty($_FILES)) {
			if(is_uploaded_file($_FILES['factura']['tmp_name'])) {
				$sourcePath = $_FILES['factura']['tmp_name'];

				// Convert timestamp into MD5 filename
				$timestamp = microtime();
				$salt = 'PatricioToledoRockz';
				$filename = md5($salt.$timestamp) . '.pdf';

				$res = array();
				$targetPath = $facturasPath.$filename;
				if(!move_uploaded_file($sourcePath,$targetPath)){
					$res["status"] = 'NOT_UPLOADED';
					echo json_encode($res);
				}
				else{
					$res["status"] = 'OK';

					// Session Variables so we can close the session and DONT LOCK IT!!!
					$session_id_usuario = $_SESSION["id_usuario"];
					$session_id_comercio = $_SESSION["id_comercio"];
					$session_concesionario = $_SESSION["concesionario"];
					$session_codigoSap = $_SESSION["codigoSap"];

					session_write_close();

					// Send but keep processing
					ignore_user_abort(true);
					set_time_limit(0);

					ob_start();
					// do initial processing here

					echo json_encode($res); // send the response


					header('Connection: close');
					header('Content-Length: '.ob_get_length());
					ob_end_flush();
					ob_flush();
					flush();

					// now the request is sent to the browser, but the script is still running
					// so, you can continue...

					mysql_select_db("TOYOTA_PLATFORM",$conn);
					mysql_set_charset('utf8',$conn);

					// Seguir en background el ingreso en DB, posteriormente WS pdf2json y luego update en DB con esos datos obtenidos
					// Insert Into datos (stock, vin, numMotor, modelo, color)

					// Insert datos (solo provenientes de la row del WS de Toyota)
					$sql = "insert into datos(stock, vin, numMotor, modelo, color, documento, Satisface_id_comercio, Satisface_concesionario, Satisface_codigoSap) values('".$stock."', '".$vin."', '".$numMotor."', '".$modelo."', '".$color."', '".$filename."', '".$_SESSION["id_comercio"]."', '".$_SESSION["concesionario"]."', '".$_SESSION["codigoSap"]."')";
					$query = mysql_query($sql);
					$datos_id = mysql_insert_id();

					// Insert HISTORIAL_INGRESO_FACTURA
					$sql = "insert into HISTORIAL_INGRESO_FACTURA(datos_id, vin, fecha_ingreso, Satisface_id_usuario) values('".$datos_id."', '".$vin."', NOW(), '".$session_id_usuario."')";
					$query = mysql_query($sql);
					

					// Request a WS pdf2json
					// Params: $filename, $_SESSION["id_comercio"], $_SESSION["concesionario"], $_SESSION["codigoSap"]

					$service_url = 'http://'.$WShostname.':'.$WSport.'/getFacturaInfo';
					$curl = curl_init($service_url);
					$curl_post_data = array();


						// POST DATA Params:
					$curl_post_data["filename"] = $filename;

					$curl_post_data["id_comercio"] = $session_id_comercio;
					$curl_post_data["concesionario"] = $session_concesionario;
					$curl_post_data["codigoSap"] = $session_codigoSap;


					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($curl, CURLOPT_POST, true);
					curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);
					$curl_response = curl_exec($curl);
					if ($curl_response === false) {
						$info = curl_getinfo($curl);
						curl_close($curl);
						die('error occured during curl exec. Additioanl info: ' . var_export($info));
					}
					curl_close($curl);
					$decoded = json_decode($curl_response);
					if (isset($decoded->response->status) && $decoded->response->status == 'ERROR') {
						die('error occured: ' . $decoded->response->errormessage);
					}

					// PDF2JSON WS getFacturaInfo - SUCCESSFULLY EXECUTED
					// Lets save Factura with gathered data!!!

					$info = $decoded->info;
					if($info->status == 'unknown'){
						echo "DONE";
					}
					else{
						// Update datos con info extraida
						$numFactura = $info->numFactura;

						$rut_numeros = $info->rut_numeros;
						$rut_verificador = $info->rut_verificador;
						$rut = $rut_numeros . $rut_verificador;

						$fecha_emision = $info->fecha_emision;
						$nombre = $info->nombre;
						$direccion = $info->direccion;
						$comuna = $info->comuna;
						$telefono = $info->telefono;
						$email = $info->email;
						$total = $info->total;

						$sql = "update datos set status = '2', numFactura = '".$numFactura."', fechaEmision = '".$fecha_emision."', total = '".$total."', rutComprador = '".$rut."', nombreComprador = '".$nombre."', direccionComprador = '".$direccion."', comunaComprador = '".$comuna."', telefonoComprador = '".$telefono."', emailComprador = '".$email."' where id='".$datos_id."'";
						$query = mysql_query($sql);
						echo "DONE";
					}

				}
			}
		}
	}
	else if($action == 'uploadCAV'){
		// Row Information
		$datos_id = $_REQUEST["datos_id"];

		if(!empty($_FILES)) {
			if(is_uploaded_file($_FILES['factura']['tmp_name'])) {
				$sourcePath = $_FILES['factura']['tmp_name'];

				// Convert timestamp into MD5 filename
				$timestamp = microtime();
				$salt = 'PatricioToledoRockz';
				$filename = md5($salt.$timestamp) . '.pdf';

				$res = array();
				$targetPath = $cavPath.$filename;
				if(!move_uploaded_file($sourcePath,$targetPath)){
					$res["status"] = 'NOT_UPLOADED';
					echo json_encode($res);
				}
				else{
					$res["status"] = 'OK';

					// Session Variables so we can close the session and DONT LOCK IT!!!
					$session_cav_id_usuario = $_SESSION["cav_id_usuario"];

					session_write_close();

					// Send but keep processing
					ignore_user_abort(true);
					set_time_limit(0);

					ob_start();
					// do initial processing here

					echo json_encode($res); // send the response


					header('Connection: close');
					header('Content-Length: '.ob_get_length());
					ob_end_flush();
					ob_flush();
					flush();

					// now the request is sent to the browser, but the script is still running
					// so, you can continue...

					mysql_select_db("TOYOTA_PLATFORM",$conn);
					mysql_set_charset('utf8',$conn);

					// Seguir en background el ingreso en DB, posteriormente WS pdf2json y luego update en DB con esos datos obtenidos
					// Insert Into CAV (documento)
					$sql = "insert into CAV(documento, fechaIngresoCav, USUARIO_CAV_id) values('".$filename."', NOW(), '".$session_cav_id_usuario."')";
					$query = mysql_query($sql);
					$CAV_id = mysql_insert_id();

					// Update datos con CAV_id insertado
					$sql = "update datos set CAV_id = '".$CAV_id."' where datos.id = '".$datos_id."'";
					$query = mysql_query($sql);

					// Request a WS pdf2json
					// Params: $filename, $_SESSION["id_comercio"], $_SESSION["concesionario"], $_SESSION["codigoSap"]

					$service_url = 'http://'.$WShostname.':'.$WSport.'/getCAVInfo';
					$curl = curl_init($service_url);
					$curl_post_data = array();


					// POST DATA Params:
					$curl_post_data["filename"] = $filename;


					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($curl, CURLOPT_POST, true);
					curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);
					$curl_response = curl_exec($curl);
					if ($curl_response === false) {
						$info = curl_getinfo($curl);
						curl_close($curl);
						die('error occured during curl exec. Additioanl info: ' . var_export($info));
					}
					curl_close($curl);
					$decoded = json_decode($curl_response);
					if (isset($decoded->response->status) && $decoded->response->status == 'ERROR') {
						die('error occured: ' . $decoded->response->errormessage);
					}

					// PDF2JSON WS getCAVInfo - SUCCESSFULLY EXECUTED
					// Lets save CAV with gathered data!!!

					$info = $decoded->info;

					// Update datos con info extraida
					// Vehiculo
					$patente = $info->vehiculo->patente;
					$tipoVehiculo = $info->vehiculo->tipovehiculo;
					$ano = $info->vehiculo->ano;
					$marca = $info->vehiculo->marca;
					$modelo = $info->vehiculo->modelo;
					$numMotor = $info->vehiculo->numMotor;
					$vin = $info->vehiculo->numChasis;
					$color = $info->vehiculo->color;
					// Propietario
					$nombre = $info->propietario->nombre;
					
					$rut_numeros = $info->propietario->rut_numeros;
					$rut_verificador = $info->propietario->rut_verificador;
					$rut = $rut_numeros . $rut_verificador;

					$fechaAdquisicion = $info->propietario->fechaAdquisicion;

					$sql = "update CAV set patente = '".$patente."', tipoVehiculo = '".$tipoVehiculo."', ano = '".$ano."', marca = '".$marca."', modelo = '".$modelo."', numMotor = '".$numMotor."', vin = '".$vin."', color = '".$color."', nombrePropietario = '".$nombre."', rutPropietario = '".$rut."', fechaAdquisicion = '".$fechaAdquisicion."' where id = '".$CAV_id."'";
					$query = mysql_query($sql);
					echo "DONE";

				}
			}
		}
	}
	else if($action == 'getHistorialData'){
		$patente = $_REQUEST["patente"];
		mysql_select_db("TOYOTA_PLATFORM",$conn);
		mysql_set_charset('utf8',$conn);

		$info = array();

		// Se asume que lo que se busca, son stock que ya han llegado hasta la etapa de Ingreso Patente
		// Por lo tanto, se muestran sus datos + Historiales y si aplica CAV
		$hasCAV = '0';
		$hasAnulacion = '0';
		$sql = "select *, date_format(datos.fechaEmision, '%d-%m-%Y') as fechaEmisionFormated from datos where patente = '".$patente."' and Satisface_id_comercio = '".$_SESSION["id_comercio"]."' and Satisface_concesionario = '".$_SESSION["concesionario"]."' and Satisface_codigoSap = '".$_SESSION["codigoSap"]."'";
		$query = mysql_query($sql);
		$cantResults = mysql_num_rows($query);
		if($cantResults == 0){
			$data = array();
			$data["err"] = "No hay resultados para la patente ingresada.";
			echo json_encode($data);
		}
		else{
			$results = mysql_fetch_array($query, MYSQL_ASSOC);
			$datosArr = array();
			$datosArr = $results;
			if($results["CAV_id"] != '0' && $results["CAV_id"] != '-1'){
				$hasCAV = '1';
			}

			if($hasCAV == '1'){
				$sql = "select CAV.*, date_format(CAV.fechaAdquisicion, '%d-%m-%Y') as fechaAdquisicionFormated, date_format(CAV.fechaIngresoCav, '%d-%m-%Y') as fechaIngresoCavFormated from CAV, datos where CAV.id = '".$datosArr["CAV_id"]."' and CAV.id = datos.CAV_id and Satisface_id_comercio = '".$_SESSION["id_comercio"]."' and Satisface_concesionario = '".$_SESSION["concesionario"]."' and Satisface_codigoSap = '".$_SESSION["codigoSap"]."'";
				$query = mysql_query($sql);
				$results = mysql_fetch_array($query, MYSQL_ASSOC);
				$cavArr = array();
				$cavArr = $results;
				$fechaIngresoCav = $cavArr["fechaIngresoCavFormated"];
				$datosArr["numMotor"] = $results["numMotor"];


				$sql = "select nombre from USUARIO_CAV where id = '".$cavArr["USUARIO_CAV_id"]."'";
				$query = mysql_query($sql);
				$results = mysql_fetch_array($query, MYSQL_ASSOC);
				$usuarioIngresoCAV = $results["nombre"];
			}

			$sql = "select HISTORIAL_ANULACION_VENTA.*, date_format(HISTORIAL_ANULACION_VENTA.fecha, '%d-%m-%Y') as fechaFormated from HISTORIAL_ANULACION_VENTA, datos where HISTORIAL_ANULACION_VENTA.vin = '".$datosArr["vin"]."' and HISTORIAL_ANULACION_VENTA.vin = datos.vin and Satisface_id_comercio = '".$_SESSION["id_comercio"]."' and Satisface_concesionario = '".$_SESSION["concesionario"]."' and Satisface_codigoSap = '".$_SESSION["codigoSap"]."' order by HISTORIAL_ANULACION_VENTA.fecha DESC limit 0,1";
			$query = mysql_query($sql);
			$cantResults = mysql_num_rows($query);
			$results = mysql_fetch_array($query, MYSQL_ASSOC);
			if($cantResults != 0){
				$hasAnulacion = '1';
				$anulacionArr = array();
				$anulacionArr = $results;
			}

			$sql = "select HISTORIAL_INGRESO_FACTURA.*, date_format(HISTORIAL_INGRESO_FACTURA.fecha_ingreso, '%d-%m-%Y') as fecha_ingresoFormated from HISTORIAL_INGRESO_FACTURA, datos where HISTORIAL_INGRESO_FACTURA.vin = '".$datosArr["vin"]."' and HISTORIAL_INGRESO_FACTURA.vin = datos.vin and Satisface_id_comercio = '".$_SESSION["id_comercio"]."' and Satisface_concesionario = '".$_SESSION["concesionario"]."' and Satisface_codigoSap = '".$_SESSION["codigoSap"]."' order by HISTORIAL_INGRESO_FACTURA.fecha_ingreso DESC limit 0,1";
			$query = mysql_query($sql);
			$results = mysql_fetch_array($query, MYSQL_ASSOC);
			$fechaIngresoFactura = $results["fecha_ingresoFormated"];
			$userIdIngresoFactura = $results["Satisface_id_usuario"];

			mysql_select_db("Satisface",$conn);
			mysql_set_charset('utf8',$conn);

			$sql = "select nombre from USUARIOS where id_usuario = '".$anulacionArr["Satisface_id_usuario"]."'";
			$query = mysql_query($sql);
			$results = mysql_fetch_array($query, MYSQL_ASSOC);
			$anulacionArr["usuarioAnulacion"] = $results["nombre"];

			$sql = "select nombre from USUARIOS where id_usuario = '".$userIdIngresoFactura."'";
			$query = mysql_query($sql);
			$results = mysql_fetch_array($query, MYSQL_ASSOC);
			$usuarioIngresoFactura = $results["nombre"];

			
			$info["datosArr"] = $datosArr;

			if($hasAnulacion == '1'){
				$info["anulacionArr"] = $anulacionArr;
			}

			if($hasCAV == '1'){
				$info["cavArr"] = $cavArr;
				$info["fechaIngresoCav"] = $fechaIngresoCav;
				$info["usuarioIngresoCAV"] = $usuarioIngresoCAV;
			}

			$info["fechaIngresoFactura"] = $fechaIngresoFactura;
			$info["usuarioIngresoFactura"] = $usuarioIngresoFactura;

			echo json_encode($info);
		}
	}




}

mysql_close($conn);


?>









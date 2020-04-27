<?php
$dir = "data/";
$idnames  = file_get_contents($dir."idnames");
$data = json_decode($idnames);

$scraper = new BPScrape();
$scraper->addDataToTable($data);

class BPScrape
{
	private $dsn, $pdo, $options;

	function __construct()
	{
		$this->dsn = "mysql:host=127.0.0.1;dbname=bpythons;charset=utf8mb4";
		$this->options = [
		    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		    PDO::ATTR_EMULATE_PREPARES => false,
		];

		try
		{
			$this->logger("connecting to the database", "DBA");
		    $this->pdo = new PDO($this->dsn, 'root', 'fxbg', $this->options);
		} catch (\PDOException $e) {
		    throw new \PDOException($e->getMessage(), (int)$e->getCode());
		}
	}

	public function resetTable()
	{
		try
		{
			$this->logger("resetting pythons table","DBA");
			$this->pdo->query("delete from pythons")->execute();

			$this->logger("resetting AUTO_INCREMENT count","DBA");
			$this->pdo->query("ALTER TABLE pythons AUTO_INCREMENT = 1")->execute();
		} catch(PDOException $pex)
		{
			$this->logger($pex->getMessage(), "ERR");
		}
	}

	function getImgFromId($id)
	{
		$url = "http://wizard.worldofballpythons.com/wizard/calculate?male%5B%5D=".$id;

		$this->logger("scraping id#".$id." from ".$url, "REQ");
		$req = $this->getCurlAsString("http://wizard.worldofballpythons.com/wizard/calculate?male%5B%5D=".$id);

		$this->logger("finding content..", "RGX");
		preg_match_all("/src[=]\"(http.+)\"/", $req, $m);
		return $m[1][0];
	}

	public function addDataToTable($d)
	{
		$i = 1;

		foreach($d as $python)
		{
			$this->logger("processing data chunk ".$i, "DBA");
			$id = $python[1]->content;
			$name = $python[2]->content;

			if($this->IDExits($id))
			{
				$this->logger("duplicate found, skipping chunk ".$i."\n","SKP");
				$i++;
				continue;
			}

			$img = $this->getImgFromId($id);

			if($name == NULL) { $name = "none"; $this->logger("name not found", "ERR"); }
			if($img == NULL) { $img = "none"; $this->logger("image not found", "ERR"); }
			if($id == NULL) { $id = "none"; $this->logger("id not found", "ERR"); }

			try
			{
				$this->logger("adding data chunk to pythons table", "ADD");
				$sql = "insert into pythons (python_name, python_id, python_image) VALUES (?,?,?)";
				$stmt = $this->pdo->prepare($sql);
				$stmt->execute([$name,$id,$img]);
			} catch(PDOException $pex)
			{
				$this->logger($pex->getMessage(), "ERR");
			}

			$i++;
		}

		$this->logger("finished adding chunks", "DBA");
	}

	private function IDExits($id)
	{
		try
		{
			$this->logger("checking for duplicate entry", "DBA");
			$sql = "select id from pythons where python_id = ?";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$id]);
			$res = $stmt->fetch();

			if($res != NULL) return true;

		} catch(PDOException $pex)
		{
			$this->logger($pex->getMessage(), "ERR");
		}

		return false;
	}

	private function getCurlAsString($url)
	{
		$this->logger("CURLing..", "REQ");
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "lerbot/0");

		$output = curl_exec($ch);

		if(curl_errno($ch))
		{
		    $error_msg = curl_error($ch);
		}

		if(isset($error_msg))
		{
			$this->logger($error_msg, "ERR");
		}

		curl_close($ch);

		return $output;
	}

	private function logger($msg, $symbol)
	{
		$color = "";

		switch($symbol)
		{
			default: break;
			case "DBA": $color = "\e[1;33;40m"; break;
			case "ERR": $color = "\e[0;31;40m"; break;
			case "SKP": $color = "\e[0;33;40m"; break;
			case "REQ": $color = "\e[0;37;40m"; break;
			case "ADD": $color = "\e[0;32;40m"; break;
			case "RGX": $color = "\e[1;30;40m"; break;
		}

		$msg = $color."[".$symbol."] ".$msg."\n";
		print($msg);
	}
}
?>
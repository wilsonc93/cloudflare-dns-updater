<?php
/**
 * Cloudflare DNS updater application
 * ==================================
 * 
 * This application should be called by cron to update Cloudflares DNS records
 * with the servers remote IP. It uses Cloudflares V4 API and will provide
 * output top STDOUT while processing to state what it is doing.
 * 
 * Should the service not be available or invalid data supplied. The application
 * throws exceptions which are then caught and displayed to STDOUT.
 * 
 * @link					https://github.com/wilsonc93
 * @author	Chris Wilson	<wilsonc93>
 */

if (php_sapi_name() !== 'cli') {
	die("This application should only be run through the command line.\n");
}

if (!function_exists('curl_version')) {
	die("PHP lib curl needs to be installed to run this application.\n");
}

/**
 * Cloudflare DNS updater application.
 * 
 * This application collects the remote IP address of the server to update
 * Cloudflares DNS records.
 * 
 * It uses Cloudflares V4 API to get the Zone details of the DNS record and then
 * submits the new IP address to Cloudflare to update the DNS.
 * 
 * @author	Chris Wilson	<wilsonc93>
 */
class dnsUpdate {

	/**
	 * @var	string						Cloudflare's API URL
	 */
	public $url = 'https://api.cloudflare.com/client/v4/zones/';

	/**
	 * @var	string						Remote IP Address
	 */
	public $ip = null;

	/**
	 * @var	string						Cloudflare email address
	 */
	public $email = null;

	/**
	 * @var	string						Cloudflare API key
	 */
	public $apiKey = null;

	/**
	 * @var	string						Target domain
	 */
	public $domain = null;

	/**
	 * @var	string						Target sub domain
	 */
	public $subDomain = null;

	/**
	 * @var	string						Cloudflare Zone ID
	 */
	public $zoneID = null;

	/**
	 * @var	string						Cloudflare record ID
	 */
	public $recordID = null;

	/**
	 * @var	object						Cloudflare Record
	 */
	public $record = null;

	/**
	 * Run the application.
	 * 
	 * This method runs the DNS updater application. It calls the methods within
	 * this class to collect the servers remote address and then creates a
	 * request to Cloudflare's API to update the DNS records.
	 * 
	 * @return	void
	 */
	public function run() {

		$this->getIP();
		$this->getZone();
		$this->getRecord();
		$this->updateRecord();

	}

	/**
	 * Get remote IP address.
	 * 
	 * This method attempts a request to ipify to get the servers IP address.
	 * If it cannot get the IP address, it throws an exception.
	 * 
	 * @return	void
	 * @throws	Exception				If IP address cannot be obtained
	 */
	public function getIP() {
		if (!$res = json_decode(file_get_contents('https://api.ipify.org/?format=json', false))) {
			throw new Exception("Unable to read ipify response." . PHP_EOL);
		}

		if (!isset($res->ip) || !preg_match('/\d+.\d+.\d+.\d+/', $res->ip)) {
			throw new Exception("Unable to get IP." . PHP_EOL);
		}

		$this->ip = $res->ip;
		echo "Obtained IP of {$this->ip} to update Cloudflare's DNS." . PHP_EOL;
	}

	/**
	 * Get Cloudflare Zone ID.
	 * 
	 * This method sends a request to Cloudflare to get the domains zoneID.
	 * 
	 * @return	void
	 */
	public function getZone() {
		$response = $this->request($this->url, '?name=' . urlencode($this->domain));

		$this->zoneID = $response->result[0]->id;
		echo "Obtained ZoneID {$this->zoneID}." . PHP_EOL;
	}

	/**
	 * Get Cloudflare record ID.
	 * 
	 * This method sends a request to Cloudflare to get the ID for the record.
	 * 
	 * If the subDomain argument has been provided, this will update that record
	 * otherwise it will assume that the domain is the record to update.
	 * 
	 * If the IP address has not changed, this method throws an exception.
	 * 
	 * @return	void
	 * @throws	Exception				IP address has not changed
	 */
	public function getRecord() {
		$response = $this->request($this->url, $this->zoneID . '/dns_records?type=A&name=' . urlencode(($this->subDomain ? $this->subDomain : $this->domain)));

		if ($response->result[0]->content === $this->ip) {
			throw new Exception("Record IP {$response->result[0]->content} for {$response->result[0]->name} already up to date." . PHP_EOL);
		}

		$this->record = $response->result[0];
		$this->recordID = $this->record->id;
		echo "Obtained record ID {$this->recordID} to be updated." . PHP_EOL;
	}

	/**
	 * Update Cloudflare record.
	 * 
	 * This method sends a request to Cloudflare to update the records IP.
	 * 
	 * @return	void
	 */
	public function updateRecord() {

		$this->record->content = $this->ip;

		$opts = array(
			CURLOPT_CUSTOMREQUEST	=> 'PUT',
			CURLOPT_POSTFIELDS		=> json_encode($this->record),
		);

		$response = $this->request($this->url, $this->zoneID . '/dns_records/' . $this->recordID, $opts);
		echo "Cloudflare record {$response->result->name} updated with {$response->result->content}" . PHP_EOL;
	}

	/**
	 * Send request to Cloudflare.
	 * 
	 * This method handles the sending of the requests to the Cloudflare API.
	 * 
	 * If the connection to Cloudflare was not successful, then we return an
	 * exception.
	 * 
	 * @param	string		$url		Cloudflare URL
	 * @param	string		$args		URL arguments
	 * @param	array		$opts		CURL opts array
	 * @return	object		$res		Cloudflare response
	 * @throws	Exception				Connection issue or no such record
	 */
	public function request($url, $args, $opts = null) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url . $args);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"X-Auth-Key: {$this->apiKey}",
			"X-Auth-Email: {$this->email}",
			"Content-Type: application/json",
		));

		if ($opts) {
			curl_setopt_array($ch, $opts);
		}

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			$errno = curl_errno($ch);
			$error = curl_error($ch);
			throw new Exception("Error: An error occured connecting to Cloudflare.\nError code: {$errno}\nError: {$error}" . PHP_EOL);
		}

		curl_close($ch);

		if (!$res = json_decode($result)) {
			throw new Exception("Unable to obtain Cloudflare response." . PHP_EOL);
		}

		if (!$res->success) {
			$error = (isset($res->errors[0]->error_chain[0]) ? $res->errors[0]->error_chain[0]->message : $res->errors[0]->message);
			throw new Exception("Error: {$error}" . PHP_EOL);
		}

		if (empty($res->result)) {
			throw new Exception("Error: No record was found." . PHP_EOL);
		}

		return $res;
	}

	/**
	 * Show application usage.
	 * 
	 * This displays a message showing how to use the application. It includes a
	 * list of parameters that need to be provided in order to run and the order
	 * the parameters should be in.
	 * 
	 * @return	void
	 */
	public function usage() {
		echo <<<EOS
Usage: {$_SERVER['argv'][0]} <email> <API key> <domain> [<subdomain>]

Where:
  email      Is the email address registered with Cloudflare
  API key    Is the Cloudflare API key
  domain     Is the domain to update
  subdomain  Is the subdomain to update (optional)

This application is designed to be run as a cron to automate updating dynamic IP 
addresses through Cloudflares API.

The parameters of this application should be passed in the order as shown above.
The subdomain is optional, if it is not provided the application will assume the
domain is the record to be updated.

EOS;
	}

	/**
	 * Construct the DNS updater application.
	 *
	 * This method constructs the DNS updater application and performs some
	 * basic validation on the arguments passed in and throws an exception if
	 * the validation does not pass.
	 *
	 * @return	void
	 * @throws	Exception				Email address does not pass validation
	 */
	public function __construct() {

		if ($_SERVER['argc'] !== 4 && $_SERVER['argc'] !== 5) {
			$this->usage();
			exit();
		}

		if (!preg_match('/^([a-zA-Z0-9_\-\.]+)@([a-zA-Z0-9_\-\.]+)\.([a-zA-Z]{2,5})$/', $_SERVER['argv'][1])) {
			throw new Exception("Email address {$_SERVER['argv'][1]} invalid" . PHP_EOL);
		}

		$this->email = $_SERVER['argv'][1];
		$this->apiKey = $_SERVER['argv'][2];
		$this->domain = $_SERVER['argv'][3];
		$this->subDomain = (isset($_SERVER['argv'][4]) ? $_SERVER['argv'][4] : null);
	}

}

try {
	$app = new dnsUpdate();
	$app->run();
} catch (Exception $e) {
	echo $e->getMessage();
}

?>
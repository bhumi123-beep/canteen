<?php
namespace PHPMailer\PHPMailer;

class SMTP
{
    private $connection;
    public $ErrorInfo = '';

    public function connect($host, $port = 587, $timeout = 30)
    {
        $this->connection = @fsockopen($host, (int)$port, $errno, $errstr, (float)$timeout);
        if (!$this->connection) {
            $this->ErrorInfo = 'SMTP connect failed: ' . $errstr . ' (' . $errno . ')';
            return false;
        }

        stream_set_timeout($this->connection, (int)$timeout);
        $response = $this->read();
        if (strpos($response, '220') !== 0) {
            $this->ErrorInfo = 'SMTP greeting failed: ' . $response;
            return false;
        }

        return true;
    }

    public function hello($hostname)
    {
        if (!$this->connection) {
            return false;
        }
        $this->write('EHLO ' . $hostname);
        $response = $this->read();
        return strpos($response, '250') === 0;
    }

    public function startTLS()
    {
        if (!$this->connection) {
            return false;
        }
        $this->write('STARTTLS');
        $response = $this->read();
        if (strpos($response, '220') !== 0) {
            $this->ErrorInfo = 'STARTTLS failed: ' . $response;
            return false;
        }

        $crypto = stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($crypto !== true) {
            $this->ErrorInfo = 'TLS negotiation failed.';
            return false;
        }

        return $this->hello('localhost');
    }

    public function authenticate($username, $password)
    {
        if (!$this->connection) {
            return false;
        }
        $this->write('AUTH LOGIN');
        $response = $this->read();
        if (strpos($response, '334') !== 0) {
            $this->ErrorInfo = 'SMTP AUTH LOGIN not accepted: ' . $response;
            return false;
        }

        $this->write(base64_encode($username));
        $response = $this->read();
        if (strpos($response, '334') !== 0) {
            $this->ErrorInfo = 'SMTP username rejected: ' . $response;
            return false;
        }

        $this->write(base64_encode($password));
        $response = $this->read();
        if (strpos($response, '235') !== 0) {
            $this->ErrorInfo = 'SMTP password rejected: ' . $response;
            return false;
        }

        return true;
    }

    public function mailFrom($fromAddress)
    {
        $this->write('MAIL FROM:<' . $fromAddress . '>');
        $response = $this->read();
        return strpos($response, '250') === 0;
    }

    public function rcptTo($toAddress)
    {
        $this->write('RCPT TO:<' . $toAddress . '>');
        $response = $this->read();
        return strpos($response, '250') === 0 || strpos($response, '251') === 0;
    }

    public function data($message)
    {
        $this->write('DATA');
        $response = $this->read();
        if (strpos($response, '354') !== 0) {
            $this->ErrorInfo = 'DATA command failed: ' . $response;
            return false;
        }

        $this->write($message . "\r\n.");
        $response = $this->read();
        if (strpos($response, '250') !== 0) {
            $this->ErrorInfo = 'Message send failed: ' . $response;
            return false;
        }

        return true;
    }

    public function quit()
    {
        if (!$this->connection) {
            return true;
        }
        $this->write('QUIT');
        $this->read();
        fclose($this->connection);
        $this->connection = null;
        return true;
    }

    private function write($line)
    {
        fwrite($this->connection, $line . "\r\n");
    }

    private function read()
    {
        $response = '';
        while (!feof($this->connection)) {
            $line = fgets($this->connection, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (strlen($line) < 3 || $line[3] !== '-') {
                break;
            }
        }

        return trim($response);
    }
}

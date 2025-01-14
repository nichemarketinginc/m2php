<?php

namespace Mongrel2;

class Connection
{
    private $sender_id;

    public function __construct($sender_id, $sub_addr, $pub_addr)
    {
        $this->sender_id = $sender_id;

        $ctx = new \ZMQContext();
        $reqs = $ctx->getSocket(\ZMQ::SOCKET_PULL);
        $reqs->connect($sub_addr);

        $resp = $ctx->getSocket(\ZMQ::SOCKET_PUB);
        $resp->connect($pub_addr);
        
        // The SOCKET_PUB type socket is no longer allowed to have an associated SOCKOPT_IDENTITY (PHP7 php-zmq)
        // https://github.com/zeromq/php-zmq/commit/9f23b02dea8002e20d36d14e4c9882af29ec3296#diff-e8fc933d1022cf57f2ce772e772c8725
        //
        //$resp->setSockOpt(\ZMQ::SOCKOPT_IDENTITY, $sender_id);

        $this->sub_addr = $sub_addr;
        $this->pub_addr = $pub_addr;

        $this->reqs = $reqs;
        $this->resp = $resp;
    }

    public function recv()
    {
        return Request::parse($this->reqs->recv());
    }

    public function recv_json()
    {
        $req = $this->recv();
        if (!isset($req->data)) {
            $req->data = json_decode($req->body);
        }
        return $req;
    }

    public function reply($req, $msg)
    {
        $this->send($req->sender, $req->conn_id, $msg);
    }

    public function send($uuid, $conn_id, $msg)
    {
        $header = sprintf('%s %d:%s,', $uuid, strlen($conn_id), $conn_id);
        $this->resp->send($header . " " . $msg);
    }

    public function reply_json($req, $data)
    {
        $this->send($req->sender, $req->conn_id, json_encode($data));
    }

    public function reply_http($req, $body, $code = 200, $status = "OK", $headers = null)
    {
        $this->reply($req, Tool::http_response($body, $code, $status, $headers));
    }

    public function deliver($uuid, $idents, $data)
    {
        $this->send($uuid, join(' ', $idents),  $data);
    }

    public function deliver_json($uuid, $idents, $data)
    {
        $this->deliver($uuid, $idents, json_encode($data));
    }

    public function deliver_http($uuid, $idents, $body, $code = 200, $status = "OK", $headers = null)
    {
        $this->deliver($uuid, $idents, Tool::http_response($body, $code, $status, $headers));
    }

}

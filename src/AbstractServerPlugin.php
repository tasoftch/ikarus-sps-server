<?php
/**
 * BSD 3-Clause License
 *
 * Copyright (c) 2020, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace Ikarus\SPS\Server;

use Ikarus\SPS\Exception\SPSException;
use Ikarus\SPS\Plugin\Management\PluginManagementInterface;
use Ikarus\SPS\Plugin\TearDownPluginInterface;

abstract class AbstractServerPlugin implements ServerPluginInterface, TearDownPluginInterface
{
    /** @var string */
    private $address;
    /** @var int|null  */
    private $port;
    /** @var string */
    private $startMessage;
    /** @var resource */
    protected $socket;
    private $_type;

    const SOCK_BACKLOG = 1;
    const SOCK_BUFFER_SIZE = 2048;

    private $reuseAddress = false;

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return $this->getAddress();
    }

    /**
     * AbstractIntermediatePlugin constructor.
     * @param string $address
     * @param int|null $port
     * @param string $startMessage
     */
    public function __construct(string $address, int $port = NULL, string $startMessage = 'Welcome to Remote Event Server of Ikarus SPS!')
    {
        $this->address = $address;
        $this->port = $port;
        $this->startMessage = $startMessage;
    }

    /**
     * @return bool
     */
    public function reuseAddress(): bool
    {
        return $this->reuseAddress;
    }

    /**
     * @param bool $reuseAddress
     * @return static
     */
    public function setReuseAddress(bool $reuseAddress)
    {
        $this->reuseAddress = $reuseAddress;
        return $this;
    }


    /**
     * This method should handle any incoming commands onto the SPS.
     *
     * @param $command
     * @param PluginManagementInterface $management
     * @return string
     */
    abstract protected function doCommand($command, PluginManagementInterface $management): string;

    /**
     * This method can be called to simply accept an incoming connection, read its request and sends the SPS response back.
     *
     * @param PluginManagementInterface $management
     * @return string|null
     */
    protected function trapNextCommand(PluginManagementInterface $management) {
        if(is_resource($this->socket)) {
            $msgsock = socket_accept($this->socket);
            if($msgsock) {
                $buffer = "";

                while ($out = socket_read($msgsock, static::SOCK_BUFFER_SIZE)) {
                    $buffer .= $out;
                    if(strlen($out) < static::SOCK_BUFFER_SIZE) {
                        break;
                    }
                }

                $response = $this->doCommand($buffer, $management);

                $len = strlen($response);
                $total = 0;

                while ($written = socket_write($msgsock, $response)) {
                    if($written === false)
                        break;
                    $total += $written;
                    if($total >= $len)
                        break;
                }

                socket_close($msgsock);
                return $buffer;
            }
        }
        return NULL;
    }


    /**
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @return int|null
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getStartMessage(): string
    {
        return $this->startMessage;
    }

    public function establishConnection() {
        if(NULL === $this->socket) {
            if(NULL === $this->getPort()) {
                $this->socket = $sock = @socket_create(AF_UNIX, SOCK_STREAM, 0);
                $this->_type = 1;
            }
            else {
                $this->socket = $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                $this->_type = 2;
            }

            if ($sock === false) {
                throw new SPSException( "socket_create() failed: " . socket_strerror(socket_last_error()), socket_last_error());
            }

            if($this->reuseAddress()) {
                socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
            }

            if (!@socket_bind($sock, $this->address, $this->port)) {
                $c = socket_last_error($sock);
                @$this->closeConnection();
                $this->socket = false;
                throw new SPSException( "socket_create() failed: " . socket_strerror($c) . " ($this->address)", $c);
            }

            if (!@socket_listen($sock, static::SOCK_BACKLOG)) {
                $c = socket_last_error($sock);
                @$this->closeConnection();
                $this->socket = false;
                throw new SPSException( "socket_listen() failed: " . socket_strerror($c), $c);
            }
            socket_getsockname($sock, $this->address, $this->port);
        }
        return is_resource($this->socket) ? true : false;
    }

    public function closeConnection()
    {
        if($this->_type == 1) {
            socket_close($this->socket);
            unlink($this->getAddress());
        } elseif ($this->_type == 2) {
            socket_close($this->socket);
        }
        $this->socket = NULL;
        $this->_type = 0;
    }


    public function tearDown()
    {
        $this->closeConnection();
    }
}
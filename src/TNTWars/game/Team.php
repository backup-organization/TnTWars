<?php


namespace TNTWars\game;


use pocketmine\Player;

class Team
{

    /** @var string $name */
    private $name;

    /** @var string $color */
    private $color;

    /** @var string $shipCoord */
    private $shipCoord;

    /** @var string $shipCountType */
    private $shipCountType;

    /** @var array $players */
    private $players = [];

    /**
     * Team constructor.
     * @param string $name
     * @param string $colorSymbol
     * @param string $shipCoord
     * @param string $shipCountType
     */
    public function __construct(string $name, string $colorSymbol, string $shipCoord, string $shipCountType)
    {
        $this->name = $name;
        $this->color = $colorSymbol;
        $this->shipCoord = $shipCoord;
        $this->shipCountType = $shipCountType;
    }

    /**
     * @return int
     */
    public function getShipCord() : int{
        return $this->shipCoord;
    }

    /**
     * @return string
     */
    public function getShipCountType() : string{
        return $this->shipCountType;
    }

    /**
     * @return string
     */
    public function getName() : string{
        return $this->name;
    }

    /**
     * @return string
     */
    public function getColor() : string{
        return $this->color;
    }

    /**
     * @param Player $player
     */
    public function addPlayer(Player $player) : void{
        $this->players[$player->getRawUniqueId()] = $player;
    }

    /**
     * @param Player $player
     */
    public function removePlayer(Player $player) : void{
        $this->players[$player->getRawUniqueId()] = $player;
    }

    /**
     * @return array
     */
    public function getPlayers() : array{
        return $this->players;
    }



}
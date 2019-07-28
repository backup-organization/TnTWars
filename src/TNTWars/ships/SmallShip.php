<?php


namespace TNTWars\ships;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use TNTWars\game\Game;

class SmallShip implements ShipInterface
{

    /** @var Game $game */
    private $game;

    /** @var array $blockCache */
    private $blockCache = [];

    /** @var Level $level */
    private $level;

    const LENGHT = 5;

    /**
     * SmallShip constructor.
     * @param Game $game
     */
    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    public function isCollided(): bool
    {
        return false; //todo: check the blocks
    }

    /**
     * @param Player $player
     */
    public function place(Player $player): void
    {
        $position = null;
        $playerTeam = null;
        foreach($this->game->teams as $team){
            if(isset($team->getPlayers()[$player->getRawUniqueId()])){
                $playerTeam = $team;
            }
        }

        $spawnValue = $player->getX();

        switch($playerTeam->getShipCountType()){
            case "X";
            $position = new Vector3($spawnValue, $player->getY() - 4/*todo: calculate*/, $player->getZ());


            $this->blockCache[Block::STONE_SLAB] = $position->substract(1);
            $this->blockCache[Block::STONE_SLAB] = $position->substract(2);
            $this->blockCache[Block::STONE_SLAB] = $position->add(1);
            $this->blockCache[Block::STONE_SLAB] = $position->add(2);
            //tnts
            $this->blockCache[Block::TNT] = $position->substract(1)->add(0,1,0);
            $this->blockCache[Block::TNT] = $position->substract(2)->add(0,1,0);
            $this->blockCache[Block::TNT] = $position->add(1)->add(0,1,0);
            $this->blockCache[Block::TNT] = $position->add(2)->add(0,1,0);
            break;
            case "Z";
            $position = new Vector3($spawnValue, $player->getY() - 4/*todo: calculate*/, $player->getZ());


            $this->blockCache[Block::STONE_SLAB] = $position->substract(0, 0, 1);
            $this->blockCache[Block::STONE_SLAB] = $position->substract(0, 0, 2);
            $this->blockCache[Block::STONE_SLAB] = $position->add(0, 0, 1);
            $this->blockCache[Block::STONE_SLAB] = $position->add(0, 0, 2);
            //tnts
            $this->blockCache[Block::TNT] = $position->substract(0, 0, 1)->add(0,1,0);
            $this->blockCache[Block::TNT] = $position->substract(0, 0, 2)->add(0,1,0);
            $this->blockCache[Block::TNT] = $position->add(0, 0, 1)->add(0,1,0);
            $this->blockCache[Block::TNT] = $position->add(0, 0, 2)->add(0,1,0);

            break;
        }

        $this->blockCache[Block::DIAMOND_BLOCK] = $position;

        foreach($this->blockCache as $blockId => $position){
            $player->level->setBlock($position, Block::get(intval($blockId)));
        }
        $player->sendMessage(TextFormat::RED . "Placed");

    }

    public function move() : void{

    }

}
<?php


namespace TNTWars\game;


use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use TNTWars\ships\SmallShip;
use TNTWars\TNTWars;

class GameListener implements Listener
{

    /** @var TNTWars $plugin */
    private $plugin;

    /**
     * GameListener constructor.
     * @param TNTWars $plugin
     */
    public function __construct(TNTWars $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param SignChangeEvent $event
     */
    public function onSignChange(SignChangeEvent $event) : void{
        $player = $event->getPlayer();
        $sign = $event->getBlock();

        if($event->getLine(0) == "[tntwars]" && $event->getLine(1) !== ""){
            if(!in_array($event->getLine(1), array_keys($this->plugin->games))){
                $player->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Arena doesn't exist!");
                return;
            }

            $dataFormat = $sign->getX() . ":" . $sign->getY() . ":" . $sign->getZ() . ":" . $player->level->getFolderName();
            $this->plugin->signs[$event->getLine(1)][] = $dataFormat;

            $location = $this->plugin->getDataFolder() . "arenas/" . $event->getLine(1) . ".json";
            if(!is_file($location)){
                //wtf ??
                return;
            }

            $fileContent = file_get_contents($location);
            $jsonData = json_decode($fileContent, true);
            $positionData = [
                "signs" => $this->plugin->signs[$event->getLine(1)]
            ];

            file_put_contents($location, json_encode(array_merge($jsonData, $positionData)));
            $player->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "Sign created");

        }
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onInteract(PlayerInteractEvent $event) : void{
        $player = $event->getPlayer();
        $block = $event->getBlock();

        foreach($this->plugin->signs as $arena => $positions){
            foreach($positions as $position) {
                $pos = explode(":", $position);
                if ($block->getX() == $pos[0] && $block->getY() == $pos[1] && $block->getZ() == $pos[2] && $player->level->getFolderName() == $pos[3]) {
                    $game = $this->plugin->games[$arena];

                    $game->join($player);
                }
            }
        }

        foreach($this->plugin->games as $name => $game){
            if(isset($game->players[$player->getRawUniqueId()]) && $game->getState() == Game::STATE_RUNNING){
                $ship = new SmallShip($game);
                $ship->place($player);
            }
        }
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event) : void{
        $player = $event->getPlayer();
        foreach($this->plugin->games as $game){
            if(in_array($player->getRawUniqueId(), array_keys(array_merge($game->players, $game->spectators)))){
                $game->quit($player);
            }
        }
    }

    /**
     * @param EntityLevelChangeEvent $event
     */
    public function onEntityLevelChange(EntityLevelChangeEvent $event) : void{
        $player = $event->getEntity();
        if(!$player instanceof Player){
            return;
        }

        $target = $event->getTarget();
        foreach($this->plugin->games as $game){
            if(in_array($player->getRawUniqueId(), array_keys(array_merge($game->players, $game->spectators)))){
                $game->quit($player);
            }
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event) : void
    {
        $player = $event->getPlayer();
        foreach ($this->plugin->games as $game) {
            if (isset($game->players[$player->getRawUniqueId()])) {
                $event->setCancelled();
            }
        }

    }

    /**
     * @param BlockPlaceEvent $event
     */
    public function onPlace(BlockPlaceEvent $event) : void{
        $player = $event->getPlayer();
        foreach ($this->plugin->games as $game) {
            if (isset($game->players[$player->getRawUniqueId()])) {
                $event->setCancelled();
            }
        }
    }

    /**
     * @param EntityDamageEvent $event
     */
    public function onDamage(EntityDamageEvent $event) : void{
        $entity = $event->getEntity();
        if(!$entity instanceof Player)return;
        foreach ($this->plugin->games as $game) {
            if (isset($game->players[$entity->getRawUniqueId()])) {
                $event->setCancelled();
            }
        }

    }
}
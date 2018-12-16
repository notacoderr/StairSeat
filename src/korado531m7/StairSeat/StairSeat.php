<?php
namespace korado531m7\StairSeat;

use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\SetEntityLinkPacket;
use pocketmine\network\mcpe\protocol\types\EntityLink;
use pocketmine\plugin\PluginBase;

class StairSeat extends PluginBase implements Listener{
    private $sit = [];
    
    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
    
    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        if($this->isSitting($player)){
            $this->unsetSitting($player);
        }
    }
    
    public function onInteract(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        if(!$this->isSitting($player)){
            $block = $event->getBlock();
            $id = $block->getId();
            if($id === BlockIds::WOODEN_STAIRS){
                $eid = Entity::$entityCount++;
                $this->setSitting($player, $block, $eid);
                $player->sendTip('Tap jump to exit the seat');
            }
        }
    }
    
    //NOTE: I want to call such a related packet or event but it doesn't work so i use playermoveevent instead.
    public function onJump(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        $to = (int) $event->getTo()->getY();
        $from = (int) $event->getFrom()->getY();
        if($this->isSitting($player) && abs(microtime(true) - $this->getSitPlayerTime($player)) > 1.5 && $from !== $to){
            $this->unsetSitting($player);
        }
    }
    
    private function getSitPlayerTime(Player $player) : int{
        return $this->sit[$player->getName()][1];
    }
    
    private function getSitPlayerId(Player $player) : int{
        return $this->sit[$player->getName()][0];
    }
    
    private function setSitPlayerId(Player $player, int $id) : void{
        $this->sit[$player->getName()] = [$id, microtime(true)];
    }
    
    private function isSitting(Player $player) : bool{
        return array_key_exists($player->getName(), $this->sit);
    }
    
    private function unsetSitting(Player $player){
        $id = $this->getSitPlayerId($player);
        $pk = new SetEntityLinkPacket();
        $entLink = new EntityLink();
        $entLink->fromEntityUniqueId = $id;
        $entLink->toEntityUniqueId = $player->getId();
        $entLink->immediate = true;
        $entLink->type = EntityLink::TYPE_REMOVE;
        $pk->link = $entLink;
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
        $pk = new RemoveEntityPacket();
        $pk->entityUniqueId = $id;
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(),$pk);
        unset($this->sit[$player->getName()]);
    }
    
    private function setSitting(Player $player, Block $block, int $id){
        $pk = new AddEntityPacket();
        $pk->entityRuntimeId = $id;
        $pk->type = 10;
        $pk->position = $block->add(0.5, 2, 0.5);
        $pk->metadata = [Entity::DATA_FLAGS => 
                            [Entity::DATA_TYPE_LONG, 1 << Entity::DATA_FLAG_IMMOBILE | 1 << Entity::DATA_FLAG_SILENT | 1 << Entity::DATA_FLAG_INVISIBLE]
                        ];
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
        $pk = new SetEntityLinkPacket();
        $entLink = new EntityLink();
        $entLink->fromEntityUniqueId = $id;
        $entLink->toEntityUniqueId = $player->getId();
        $entLink->immediate = true;
        $entLink->type = EntityLink::TYPE_RIDER;
        $pk->link = $entLink;
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
        $this->setSitPlayerId($player, $id);
    }
}
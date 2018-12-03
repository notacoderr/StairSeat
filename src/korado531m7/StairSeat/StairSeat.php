<?php
namespace korado531m7\StairSeat;

use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
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
    
    public function onToggleSneak(PlayerToggleSneakEvent $event){
        $player = $event->getPlayer();
        if($this->isSitting($player)){
            $this->unsetSitting($player);
        }else{
            if($player->level->getBlock($player->subtract(0, 1))->getId() === 53){
                $id = Entity::$entityCount++;
                $this->setPlayerasSitting($player, $id);
                $this->setSitting($player, $id);
                $player->sendMessage('sit');
            }
        }
    }
    
    private function getSitPlayerId(Player $player) : int{
        return $this->sit[$player->getName()];
    }
    
    private function setSitting(Player $player, int $id){
        $player->setImmobile();
        $this->sit[$player->getName()] = $id;
    }
    
    private function unsetSitting(Player $player){
        $player->setImmobile(false);
        $this->removePlayer($this->getSitPlayerId($player));
        unset($this->sit[$player->getName()]);
    }
    
    private function isSitting(Player $player) : bool{
        return array_key_exists($player->getName(), $this->sit);
    }
    
    private function setPlayerasSitting(Player $player, int $id){
        $pk = new AddEntityPacket();
        $pk->entityRuntimeId = $id;
        $pk->type = 10;
        $pk->position = $player->add(0, 1.1);
        $pk->metadata = [Entity::DATA_FLAGS => 
                            [Entity::DATA_TYPE_LONG, 1 << Entity::DATA_FLAG_IMMOBILE | 1 << Entity::DATA_FLAG_SILENT | 1 << Entity::DATA_FLAG_INVISIBLE]
                        ];
        $player->dataPacket($pk);
        $pk = new SetEntityLinkPacket();
        $entLink = new EntityLink();
        $entLink->fromEntityUniqueId = $id;
        $entLink->toEntityUniqueId = $player->getId();
        $entLink->immediate = true;
        $entLink->type = EntityLink::TYPE_RIDER;
        $pk->link = $entLink;
        $player->dataPacket($pk);
    }
    
    private function removePlayer(int $id){
        $pk = new RemoveEntityPacket();
        $pk->entityUniqueId = $id;
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(),$pk);
        
    }
}
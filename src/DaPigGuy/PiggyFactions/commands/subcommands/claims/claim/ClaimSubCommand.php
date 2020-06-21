<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyFactions\commands\subcommands\claims\claim;

use DaPigGuy\PiggyFactions\commands\subcommands\FactionSubCommand;
use DaPigGuy\PiggyFactions\event\claims\ChunkOverclaimEvent;
use DaPigGuy\PiggyFactions\event\claims\ClaimChunkEvent;
use DaPigGuy\PiggyFactions\factions\Faction;
use DaPigGuy\PiggyFactions\players\FactionsPlayer;
use pocketmine\level\format\Chunk;
use pocketmine\Player;

class ClaimSubCommand extends FactionSubCommand
{
    public function onNormalRun(Player $sender, ?Faction $faction, FactionsPlayer $member, string $aliasUsed, array $args): void
    {
        if (in_array($sender->getLevel()->getFolderName(), $this->plugin->getConfig()->getNested("factions.claims.blacklisted-worlds"))) {
            $member->sendMessage("commands.claim.blacklisted-world");
            return;
        }
        if (!$member->isInAdminMode()) {
            if ($faction->getPower() / $this->plugin->getConfig()->getNested("factions.claim.cost", 1) < count($this->plugin->getClaimsManager()->getFactionClaims($faction)) + 1) {
                $member->sendMessage("commands.claim.no-power");
                return;
            }
            if (count($this->plugin->getClaimsManager()->getFactionClaims($faction)) >= ($max = $this->plugin->getConfig()->getNested("factions.claims.max", -1)) && $max !== -1) {
                $member->sendMessage("commands.claim.max-claimed");
                return;
            }
        }
        $claim = $this->plugin->getClaimsManager()->getClaimByPosition($sender);
        if ($claim !== null) {
            if ($claim->canBeOverClaimed() && $claim->getFaction() !== $faction) {
                $adjacentChunks = $sender->getLevel()->getAdjacentChunks($claim->getChunkX(), $claim->getChunkZ());
                foreach ($adjacentChunks as $chunk) {
                    if ($chunk instanceof Chunk) {
                        $adjacentClaim = $this->plugin->getClaimsManager()->getClaim($chunk->getX(), $chunk->getZ(), $sender->getLevel()->getFolderName());
                        if ($adjacentClaim !== null && $adjacentClaim->getFaction() === $faction) {
                            $ev = new ChunkOverclaimEvent($faction, $member, $claim);
                            $ev->call();
                            if ($ev->isCancelled()) return;

                            $member->sendMessage("commands.claim.over-claimed");
                            $claim->setFaction($faction);
                            return;
                        }
                    }
                }
            }
            $member->sendMessage("commands.claim.already-claimed");
            return;
        }
        $ev = new ClaimChunkEvent($faction, $member, $sender->chunk->getX(), $sender->chunk->getZ());
        $ev->call();
        if ($ev->isCancelled()) return;

        $this->plugin->getClaimsManager()->createClaim($faction, $sender->getLevel(), $sender->chunk->getX(), $sender->chunk->getZ());
        $member->sendMessage("commands.claim.success");
    }

    protected function prepare(): void
    {
        $this->registerSubCommand(new ClaimAutoSubCommand($this->plugin, "auto", "Automatically claim chunks", ["a"]));
        $this->registerSubCommand(new ClaimCircleSubCommand($this->plugin, "circle", "Claim chunks in a circle radius", ["c"]));
        $this->registerSubCommand(new ClaimSquareSubCommand($this->plugin, "square", "Claim chunks in a square radius", ["s"]));
    }
}
<script setup>
import ColorDot from './ColorDot.vue'

defineProps({
  players: { type: Array, required: true }, // sorted desc by score, each with a `.theme` object
})
defineEmits(['close'])

const medals = ['🥇', '🥈', '🥉']
</script>

<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal podium-modal">
      <h2>🏆 Partie terminée</h2>

      <div class="podium">
        <div v-for="(player, index) in players.slice(0, 3)" :key="player.id" :class="`podium-step podium-step-${index + 1}`">
          <div class="podium-medal">{{ medals[index] }}</div>
          <div class="podium-block">
            <ColorDot :color="player.theme?.color" />
            <span class="podium-name">{{ player.name }}</span>
            <span class="podium-score">{{ player.score }}</span>
          </div>
        </div>
      </div>

      <ul v-if="players.length > 3" class="list">
        <li v-for="(player, index) in players.slice(3)" :key="player.id" class="list-item">
          <span>#{{ index + 4 }} {{ player.name }}</span>
          <span class="podium-score">{{ player.score }}</span>
        </li>
      </ul>

      <button class="btn btn-primary" type="button" @click="$emit('close')">Fermer</button>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(5, 7, 15, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  z-index: 10;
}

.modal {
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 2rem;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  max-width: 32rem;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.25rem;
}

.podium {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 1rem;
  width: 100%;
}

.podium-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.podium-step-1 {
  order: 2;
}

.podium-step-2 {
  order: 1;
}

.podium-step-3 {
  order: 3;
}

.podium-medal {
  font-size: 2rem;
}

.podium-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm) var(--radius-sm) 0 0;
  padding: 1rem;
  width: 6rem;
  justify-content: center;
}

.podium-step-1 .podium-block {
  height: 7rem;
  background: color-mix(in srgb, gold 20%, var(--surface-2));
}

.podium-step-2 .podium-block {
  height: 5rem;
}

.podium-step-3 .podium-block {
  height: 3.5rem;
}

.podium-name {
  font-weight: 700;
  font-size: 0.9rem;
  text-align: center;
}

.podium-score {
  font-weight: 800;
  color: var(--accent);
  font-size: 1.2rem;
}
</style>

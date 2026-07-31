<script setup>
import { ref, watch } from 'vue'
import ColorDot from './ColorDot.vue'

const props = defineProps({
  question: { type: Object, required: true },
  theme: { type: Object, default: null },
})
const emit = defineEmits(['resolve'])

const showAnswer = ref(false)

watch(
  () => props.question,
  () => {
    showAnswer.value = false
  },
)
</script>

<template>
  <div class="modal-backdrop">
    <div class="modal">
      <span v-if="theme" class="badge">
        <ColorDot :color="theme.color" />
        {{ theme.name }}
      </span>

      <p class="question-text">{{ question.questionText }}</p>

      <div class="answer" :class="{ 'is-visible': showAnswer }">
        <p class="answer-label">Réponse</p>
        <p class="answer-text">{{ question.answerText }}</p>
      </div>

      <div class="actions">
        <button v-if="!showAnswer" class="btn" type="button" @click="showAnswer = true">
          Afficher la réponse
        </button>
        <button class="btn btn-success" type="button" @click="emit('resolve', true)">✓ Bonne réponse</button>
        <button class="btn btn-danger" type="button" @click="emit('resolve', false)">✗ Mauvaise réponse</button>
      </div>
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
  gap: 1.25rem;
}

.question-text {
  font-size: 1.4rem;
  font-weight: 700;
  line-height: 1.35;
}

.answer {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0;
  max-height: 0;
  overflow: hidden;
  opacity: 0;
  transition:
    max-height 0.25s ease,
    opacity 0.2s ease,
    padding 0.25s ease;
}

.answer.is-visible {
  max-height: 12rem;
  opacity: 1;
  padding: 1rem 1.25rem;
}

.answer-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-muted);
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.answer-text {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--accent);
}

.actions {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
}
</style>

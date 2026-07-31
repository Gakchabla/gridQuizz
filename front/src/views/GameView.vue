<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { apiFetch } from '../api/client'
import GridNumber from '../components/GridNumber.vue'
import QuestionModal from '../components/QuestionModal.vue'
import ColorDot from '../components/ColorDot.vue'

const props = defineProps({ id: [String, Number] })
const route = useRoute()

const session = ref(null)
const themes = ref([])
const questions = ref([])
const players = ref([])
const currentPlayer = ref(null)
const gridOrder = ref([])
const revealed = ref(true)
const loading = ref(true)
const error = ref(null)
const selectedQuestion = ref(null)

let revealTimer = null

const questionByNumber = computed(() => {
  const map = new Map()
  questions.value.forEach((q) => map.set(q.number, q))
  return map
})

const selectedTheme = computed(() => (selectedQuestion.value ? themeOf(selectedQuestion.value) : null))

function shuffle(array) {
  const result = array.slice()
  for (let i = result.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[result[i], result[j]] = [result[j], result[i]]
  }
  return result
}

function startRevealTimer() {
  clearTimeout(revealTimer)
  revealed.value = true
  revealTimer = setTimeout(() => {
    revealed.value = false
  }, session.value.revealDuration * 1000)
}

function buildGridOrder() {
  // The "shuffled" session param only controls the grid's visual layout
  // (which cell each number lands on), independently of which question a number hides.
  const numbers = questions.value.map((q) => q.number).sort((a, b) => a - b)
  gridOrder.value = session.value.shuffled ? shuffle(numbers) : numbers
}

async function fetchQuestions() {
  return Promise.all(session.value.questions.map((iri) => apiFetch(iri)))
}

async function refreshTurnState() {
  session.value = await apiFetch(`/api/sessions/${props.id}`)
  players.value = await Promise.all(session.value.players.map((iri) => apiFetch(iri)))
  currentPlayer.value = session.value.currentPlayer
    ? (players.value.find((p) => p['@id'] === session.value.currentPlayer) ?? null)
    : null
}

async function load() {
  loading.value = true
  try {
    session.value = await apiFetch(`/api/sessions/${props.id}`)
    themes.value = await Promise.all(session.value.themes.map((iri) => apiFetch(iri)))

    let currentQuestions = await fetchQuestions()
    // A number is only assigned once a game has started (see ResetSessionProcessor).
    // A null number means this session has never been played: start it now.
    // "Nouvelle partie" links here with ?new=1 to force a fresh start even when
    // a game is already in progress (numbers already assigned).
    const isFreshStart = route.query.new === '1' || currentQuestions.some((q) => q.number === null)
    if (isFreshStart) {
      await apiFetch(`/api/sessions/${props.id}/reset`, { method: 'POST' })
      currentQuestions = await fetchQuestions()
    }
    questions.value = currentQuestions
    buildGridOrder()
    await refreshTurnState()

    if (isFreshStart) {
      startRevealTimer()
    } else {
      // Resuming a game already in progress: pick up exactly where it was left,
      // without re-revealing the theme colors.
      clearTimeout(revealTimer)
      revealed.value = false
    }
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function themeOf(question) {
  return themes.value.find((t) => t['@id'] === question.theme)
}

function themeColor(question) {
  return themeOf(question)?.color ?? '#4b5563'
}

function themeOfPlayer(player) {
  return themes.value.find((t) => t['@id'] === player.theme)
}

async function selectQuestion(question) {
  if (question.answered) {
    return
  }
  selectedQuestion.value = question
  await apiFetch(question['@id'], {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/merge-patch+json' },
    body: JSON.stringify({ answered: true }),
  })
  question.answered = true
}

async function resolveQuestion(correct) {
  const question = selectedQuestion.value
  await apiFetch(`${question['@id']}/resolve`, {
    method: 'POST',
    body: JSON.stringify({ correct }),
  })
  selectedQuestion.value = null
  await refreshTurnState()
}

async function resetGame() {
  await apiFetch(`/api/sessions/${props.id}/reset`, { method: 'POST' })
  questions.value = await fetchQuestions()
  buildGridOrder()
  await refreshTurnState()
  selectedQuestion.value = null
  clearTimeout(revealTimer)
  revealed.value = false
}

onMounted(load)
onUnmounted(() => clearTimeout(revealTimer))
</script>

<template>
  <div class="page">
    <p v-if="loading" class="empty-state">Chargement...</p>
    <p v-else-if="error" class="empty-state">Erreur : {{ error }}</p>
    <template v-else>
      <div class="topbar">
        <div class="topbar-title">
          <router-link class="back-link" :to="{ name: 'session-list' }">&larr; Sessions</router-link>
          <h1>{{ session.name }}</h1>
        </div>
        <button class="btn btn-danger" type="button" @click="resetGame">↺ Réinitialiser</button>
      </div>

      <div v-if="currentPlayer" class="turn-banner">
        <span>🎯 Au tour de <strong>{{ currentPlayer.name }}</strong></span>
        <span class="badge">
          <ColorDot :color="themeOfPlayer(currentPlayer)?.color" />
          {{ themeOfPlayer(currentPlayer)?.name }}
        </span>
      </div>

      <div v-if="players.length" class="scoreboard">
        <div
          v-for="player in players"
          :key="player.id"
          class="scoreboard-item"
          :class="{ 'is-current': currentPlayer && currentPlayer.id === player.id }"
        >
          <ColorDot :color="themeOfPlayer(player)?.color" />
          <span class="scoreboard-name">{{ player.name }}</span>
          <span class="scoreboard-score">{{ player.score }}</span>
        </div>
      </div>

      <div class="legend">
        <span v-for="t in themes" :key="t.id" class="badge">
          <ColorDot :color="t.color" />{{ t.name }}
        </span>
      </div>

      <div class="grid">
        <GridNumber
          v-for="number in gridOrder"
          :key="number"
          :number="number"
          :color="revealed ? themeColor(questionByNumber.get(number)) : '#000000'"
          :disabled="questionByNumber.get(number).answered"
          @click="selectQuestion(questionByNumber.get(number))"
        />
      </div>

      <QuestionModal
        v-if="selectedQuestion"
        :question="selectedQuestion"
        :theme="selectedTheme"
        @resolve="resolveQuestion"
      />
    </template>
  </div>
</template>

<style scoped>
.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(5.5rem, 1fr));
  gap: 1rem;
}

.turn-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  font-size: 1.1rem;
}

.scoreboard {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.scoreboard-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0.5rem 0.9rem;
}

.scoreboard-item.is-current {
  border-color: var(--accent);
  box-shadow: 0 0 0 1px var(--accent);
}

.scoreboard-name {
  font-weight: 700;
}

.scoreboard-score {
  color: var(--accent);
  font-weight: 800;
}
</style>

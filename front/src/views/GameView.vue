<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { apiFetch } from '../api/client'
import GridNumber from '../components/GridNumber.vue'
import QuestionModal from '../components/QuestionModal.vue'
import PodiumModal from '../components/PodiumModal.vue'
import ColorDot from '../components/ColorDot.vue'

const GRID_GAP = 10 // px — keep in sync with the .grid CSS `gap`

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
const showPodium = ref(false)
const countdown = ref(0)

const gridEl = ref(null)
const gridSize = ref({ width: 0, height: 0 })
let resizeObserver = null

let revealTimer = null
let countdownTimer = null

function runCountdown(seconds = 10) {
  return new Promise((resolve) => {
    clearInterval(countdownTimer)
    countdown.value = seconds
    countdownTimer = setInterval(() => {
      countdown.value -= 1
      if (countdown.value <= 0) {
        clearInterval(countdownTimer)
        countdown.value = 0
        resolve()
      }
    }, 1000)
  })
}

// Based on the grid's numbers, not every Question row: a legacy hardcore
// theme (from before that mechanic was dropped) may still carry questions
// with number = null, which would otherwise block this from ever becoming true.
const allAnswered = computed(
  () => gridOrder.value.length > 0 && gridOrder.value.every((number) => questionByNumber.value.get(number)?.answered),
)

// Focus mode (sidebar hidden, grid at its biggest) covers both the color
// memorization window AND the countdown that precedes it, so the game screen
// is already in its "big grid" shape by the time the countdown appears.
const focusMode = computed(() => revealed.value || countdown.value > 0)

const rankedPlayers = computed(() =>
  [...players.value].sort((a, b) => b.score - a.score).map((p) => ({ ...p, theme: themeOfPlayer(p) })),
)

// One unified list for the sidebar: normal themes paired with their player
// (ranked by score), plus the bonus theme (no player) tacked on at the end.
// The hardcore theme is a hidden reserve (see PLAN.md) — never shown here.
const sidebarEntries = computed(() => {
  const normal = themes.value
    .filter((t) => !t.bonus && !t.hardcore)
    .map((theme) => ({ theme, player: players.value.find((p) => p.theme === theme['@id']) ?? null }))
    .sort((a, b) => (b.player?.score ?? 0) - (a.player?.score ?? 0))
  const bonus = themes.value.filter((t) => t.bonus).map((theme) => ({ theme, player: null }))
  return [...normal, ...bonus]
})

// Theme name + color, bonus last — shown as a legend during the countdown and
// the color-reveal window, since the sidebar (which normally carries this
// info via the scoreboard) is hidden throughout both. Hardcore stays hidden
// here too — it's a surprise, not something to telegraph up front.
const legendThemes = computed(() => {
  const normal = themes.value.filter((t) => !t.bonus && !t.hardcore)
  const bonus = themes.value.filter((t) => t.bonus)
  return [...normal, ...bonus]
})

const questionByNumber = computed(() => {
  const map = new Map()
  questions.value.forEach((q) => map.set(q.number, q))
  return map
})

// Pick the column count that yields the biggest square cell fitting the
// *actually measured* grid area (both width and height), instead of a size
// guessed from question count alone — that left a lot of empty space below
// the grid whenever a wide container produced few rows.
function bestColumnCount(width, height, count) {
  if (count === 0 || width <= 0 || height <= 0) {
    return Math.max(1, Math.ceil(Math.sqrt(count || 1)))
  }
  let bestCols = 1
  let bestCellSize = 0
  for (let cols = 1; cols <= count; cols++) {
    const rows = Math.ceil(count / cols)
    const cellWidth = (width - GRID_GAP * (cols - 1)) / cols
    const cellHeight = (height - GRID_GAP * (rows - 1)) / rows
    const cellSize = Math.min(cellWidth, cellHeight)
    if (cellSize > bestCellSize) {
      bestCellSize = cellSize
      bestCols = cols
    }
  }
  return bestCols
}

const gridColumns = computed(() => bestColumnCount(gridSize.value.width, gridSize.value.height, gridOrder.value.length))

const gridCellPx = computed(() => {
  const cols = gridColumns.value
  if (!cols || !gridSize.value.width) return 0
  return (gridSize.value.width - GRID_GAP * (cols - 1)) / cols
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
  // A legacy hardcore theme's questions carry number = null (see ResetSessionProcessor)
  // and must be excluded here.
  const numbers = questions.value
    .map((q) => q.number)
    .filter((n) => n !== null)
    .sort((a, b) => a - b)
  gridOrder.value = session.value.shuffled ? shuffle(numbers) : numbers
}

// One request for the whole collection (filtered by session) instead of one
// request per question/theme/player — a 9-player session has 54 questions,
// so that was up to ~70 parallel requests per load/refresh.
async function fetchCollection(resource) {
  const collection = await apiFetch(`/api/${resource}?session=${encodeURIComponent(session.value['@id'])}`)
  return collection.member
}

async function fetchQuestions() {
  return fetchCollection('questions')
}

async function refreshTurnState() {
  session.value = await apiFetch(`/api/sessions/${props.id}`)
  players.value = await fetchCollection('players')
  currentPlayer.value = session.value.currentPlayer
    ? (players.value.find((p) => p['@id'] === session.value.currentPlayer) ?? null)
    : null
}

async function load() {
  loading.value = true
  try {
    // Single request for session + themes + questions + players instead of
    // 4 separate ones: each request pays a fixed latency floor through
    // Docker Desktop's Windows host port-forwarding, so cutting round-trips
    // (not just per-request payload) is what actually moves the needle.
    const state = await apiFetch(`/api/sessions/${props.id}/state`)
    session.value = state.session
    themes.value = state.themes

    let currentQuestions = state.questions
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
    // Reveal the grid (grid area, sidebar, etc.) before counting down, so the
    // countdown overlay appears on top of the actual game screen instead of
    // running behind the "Chargement..." placeholder.
    revealed.value = false
    loading.value = false

    if (isFreshStart) {
      await runCountdown()
      startRevealTimer()
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

function ownerOf(question) {
  return players.value.find((p) => p.theme === question.theme) ?? null
}

// A question whose theme's owner is in "mode facile" can only be picked by
// that owner, on their own turn — everyone else sees it grayed out and
// unclickable, so an easy-mode player's questions can never be "stolen".
function isLockedForOthers(question) {
  const owner = ownerOf(question)
  return !!owner?.easyMode && owner['@id'] !== currentPlayer.value?.['@id']
}

// Outside the memorization window, cells are black — except once answered
// (color comes back for good, so players can see progress at a glance) and
// except for a player in "mode facile": during their turn, their own theme's
// still-unanswered questions keep showing their color, as a hint to help
// them spot their theme faster.
function cellColor(question) {
  if (revealed.value || question.answered) {
    return themeColor(question)
  }
  if (currentPlayer.value?.easyMode && question.theme === currentPlayer.value.theme) {
    return themeColor(question)
  }
  return '#000000'
}

async function selectQuestion(question) {
  if (question.answered) {
    return
  }
  await apiFetch(question['@id'], {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/merge-patch+json' },
    body: JSON.stringify({ answered: true }),
  })
  question.answered = true
  selectedQuestion.value = question
}

async function resolveQuestion(correct) {
  const question = selectedQuestion.value
  await apiFetch(`${question['@id']}/resolve`, {
    method: 'POST',
    body: JSON.stringify({ correct }),
  })
  selectedQuestion.value = null
  await refreshTurnState()
  if (allAnswered.value) {
    showPodium.value = true
  }
}

async function resetGame() {
  await apiFetch(`/api/sessions/${props.id}/reset`, { method: 'POST' })
  questions.value = await fetchQuestions()
  buildGridOrder()
  await refreshTurnState()
  selectedQuestion.value = null
  showPodium.value = false
  clearTimeout(revealTimer)
  revealed.value = false
  await runCountdown()
  startRevealTimer()
}

onMounted(async () => {
  await load()
  await nextTick()
  if (gridEl.value) {
    resizeObserver = new ResizeObserver((entries) => {
      const { width, height } = entries[0].contentRect
      gridSize.value = { width, height }
    })
    resizeObserver.observe(gridEl.value)
  }
})

onUnmounted(() => {
  clearTimeout(revealTimer)
  clearInterval(countdownTimer)
  resizeObserver?.disconnect()
})
</script>

<template>
  <div class="page game-page">
    <p v-if="loading" class="empty-state">Chargement...</p>
    <p v-else-if="error" class="empty-state">Erreur : {{ error }}</p>
    <template v-else>
      <div class="topbar">
        <div class="topbar-title">
          <router-link class="back-link" :to="{ name: 'session-list' }">&larr; Sessions</router-link>
          <h1>{{ session.name }}</h1>
        </div>
        <div class="list-item-actions">
          <button v-if="allAnswered" class="btn btn-success" type="button" @click="showPodium = true">
            🏆 Podium
          </button>
          <button class="btn btn-danger" type="button" @click="resetGame">↺ Réinitialiser</button>
        </div>
      </div>

      <div v-if="revealed && countdown === 0" class="theme-legend theme-legend-bar">
        <span v-for="theme in legendThemes" :key="theme.id" class="theme-legend-item">
          <ColorDot :color="theme.color" />
          {{ theme.name }}
        </span>
      </div>

      <div class="game-layout">
        <aside class="game-sidebar" :class="{ 'is-hidden': focusMode }">
          <div v-if="currentPlayer" class="turn-banner">
            <span>🎯 Au tour de <strong>{{ currentPlayer.name }}</strong></span>
            <span class="badge">
              <ColorDot :color="themeOfPlayer(currentPlayer)?.color" />
              {{ themeOfPlayer(currentPlayer)?.name }}
            </span>
          </div>

          <div v-if="sidebarEntries.length">
            <h2 class="sidebar-title">Classement</h2>
            <div class="scoreboard">
              <div
                v-for="entry in sidebarEntries"
                :key="entry.theme.id"
                class="scoreboard-item"
                :class="{ 'is-current': entry.player && currentPlayer && currentPlayer.id === entry.player.id }"
              >
                <ColorDot :color="entry.theme.color" />
                <div class="scoreboard-info">
                  <span class="scoreboard-name">{{ entry.player ? entry.player.name : entry.theme.name }}</span>
                  <span v-if="entry.player" class="scoreboard-theme">{{ entry.theme.name }}</span>
                </div>
                <span v-if="entry.player" class="scoreboard-score">{{ entry.player.score }}</span>
              </div>
            </div>
          </div>
        </aside>

        <div class="game-main">
          <div v-if="countdown > 0" class="countdown-overlay">
            <span :key="countdown" class="countdown-number">{{ countdown }}</span>
            <div class="theme-legend">
              <span v-for="theme in legendThemes" :key="theme.id" class="theme-legend-item is-lg">
                <ColorDot :color="theme.color" />
                {{ theme.name }}
              </span>
            </div>
          </div>
          <div
            ref="gridEl"
            class="grid"
            :class="{ 'is-focus': focusMode }"
            :style="{
              gridTemplateColumns: `repeat(${gridColumns}, 1fr)`,
              '--cell-px': gridCellPx + 'px',
            }"
          >
            <GridNumber
              v-for="number in gridOrder"
              :key="number"
              :number="number"
              :color="cellColor(questionByNumber.get(number))"
              :hide-number="countdown > 0"
              :answered="questionByNumber.get(number).answered"
              :disabled="
                questionByNumber.get(number).answered ||
                countdown > 0 ||
                isLockedForOthers(questionByNumber.get(number))
              "
              @click="selectQuestion(questionByNumber.get(number))"
            />
          </div>
        </div>
      </div>

      <QuestionModal
        v-if="selectedQuestion"
        :question="selectedQuestion"
        :theme="selectedTheme"
        @resolve="resolveQuestion"
      />

      <PodiumModal v-if="showPodium" :players="rankedPlayers" @close="showPodium = false" />
    </template>
  </div>
</template>

<style scoped>
.game-page {
  max-width: 100rem;
  min-height: 100vh;
  padding: 1.5rem;
  /* Tighter than the .page default (2.75rem): keeps the topbar close to the
     game area, and the theme legend bar close to the grid during the
     memorization phase. */
  gap: 1rem;
}

.game-layout {
  display: flex;
  /* stretch (not flex-start): .game-main must receive the layout's full
     height, otherwise the grid sizes off its own content instead of the
     actually available space and the "everything must fit" guarantee breaks. */
  align-items: stretch;
  flex: 1;
  min-height: 0;
}

.game-sidebar {
  /* Width lives here (not on .game-layout's gap) so it — and the gap next to
     it — can animate away together while the theme colors are memorized. */
  flex: 0 0 auto;
  width: 16rem;
  margin-right: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  position: sticky;
  top: 1.5rem;
  max-height: calc(100vh - 3rem);
  overflow: hidden auto;
  opacity: 1;
  transition:
    width 0.45s ease,
    margin-right 0.45s ease,
    max-height 0.45s ease,
    opacity 0.3s ease;
}

.game-sidebar.is-hidden {
  width: 0;
  margin-right: 0;
  /* Without this, the hidden sidebar still reports its natural content
     height, which `align-items: stretch` on .game-layout uses to stretch
     .game-main taller than the grid actually needs — pushing the grid down
     (via .game-main's `justify-content: center`) and opening up a big gap
     under the theme legend bar above it. */
  max-height: 0;
  opacity: 0;
  pointer-events: none;
}

.game-main {
  position: relative;
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  /* Centers the (max-height-capped) grid when the available area is taller
     than 80vh, instead of leaving the leftover space stuck at the bottom. */
  align-items: center;
  justify-content: center;
}

.countdown-overlay {
  position: absolute;
  inset: 0;
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1.5rem;
  background: rgba(11, 15, 30, 0.75);
  border-radius: var(--radius);
}

.countdown-number {
  font-size: clamp(5rem, 22vw, 14rem);
  font-weight: 900;
  color: var(--accent);
  text-shadow: 0 8px 30px rgba(139, 92, 246, 0.6);
  animation: countdown-pulse 1s ease-out;
}

@keyframes countdown-pulse {
  from {
    transform: scale(1.5);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.theme-legend {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  max-width: 90%;
}

.theme-legend-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 0.35rem 0.8rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
}

/* Much bigger during the countdown, where it's the main thing on screen. */
.theme-legend-item.is-lg {
  font-size: 1.75rem;
  padding: 0.7rem 1.4rem;
  gap: 0.7rem;
}

.theme-legend-item.is-lg :deep(.badge-dot) {
  width: 1.3rem;
  height: 1.3rem;
}

/* Standalone bar variant: shown once colors are revealed (no more countdown
   backdrop) so the theme/color mapping stays visible while the sidebar is
   still hidden. Sits as a normal block above `.game-layout` (a sibling, not
   nested inside `.game-main`/`.grid`'s own sizing machinery) so it takes real
   space without shrinking the grid — if that pushes the page past the
   viewport, it scrolls naturally instead. */
.theme-legend-bar {
  background: rgba(11, 15, 30, 0.55);
  backdrop-filter: blur(4px);
  padding: 0.6rem 1rem;
  border-radius: var(--radius);
}

@media (max-width: 768px) {
  .game-layout {
    flex-direction: column;
  }

  .game-sidebar {
    flex: none;
    width: 100%;
    margin-right: 0;
    position: static;
    /* Capped so a long player/theme list can never eat into the grid's share
       of the screen — it scrolls internally instead. */
    max-height: 30vh;
  }

  .game-sidebar.is-hidden {
    max-height: 0;
    margin-bottom: 0;
  }
}

.sidebar-title {
  font-size: 0.8rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  border: none;
  padding: 0;
  margin-bottom: 0.6rem;
}

.grid {
  flex: 1;
  max-height: 75vh;
  transition: max-height 0.45s ease;
  display: grid;
  gap: 0.625rem;
  align-content: center;
  justify-content: center;
}

.grid.is-focus {
  /* Sidebar is hidden during memorization, so the grid can claim the space it
     freed up — capped just under 100vh to leave breathing room top/bottom. */
  max-height: 92vh;
}

.turn-banner {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.5rem;
  font-size: 1.05rem;
}

.scoreboard {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
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

.scoreboard-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.scoreboard-name {
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.scoreboard-theme {
  font-size: 0.75rem;
  color: var(--text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.scoreboard-score {
  color: var(--accent);
  font-weight: 800;
  margin-left: auto;
}
</style>

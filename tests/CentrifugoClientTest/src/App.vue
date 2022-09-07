<script setup lang="ts">
import { Centrifuge } from 'centrifuge';
import { ref } from 'vue';

const subscribeTokenEndpoint = 'http://127.0.0.1/broadcasting/auth'
const connectionTokenEndpoint = 'http://127.0.0.1/api/connection_token'

function getToken(endpoint, ctx) {
  console.log('ctx', ctx)
  return new Promise((resolve, reject) => {
    console.log('start fetch')
    fetch(endpoint, {
      method: 'POST',
      headers: new Headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(ctx)
    })
        .then(res => {
          if (!res.ok) {
            throw new Error(`Unexpected status code ${res.status}`);
          }
          return res.json();
        })
        .then(data => {
          console.log('data', data.token, data)
          resolve(data.token);
        })
        .catch(err => {
          reject(err);
        });
  });
}

const connected = ref(false)
const subscribed = ref('not subscribed')

const centrifuge = new Centrifuge('ws://localhost:8001/connection/websocket', {
  debug: true,
  getToken: function (ctx) {
    console.log('connectionTokenEndpoint', connectionTokenEndpoint)
    return getToken(connectionTokenEndpoint, ctx);
  },
  timeout: 3000,
})

const sub = centrifuge.newSubscription('test:test', {
  getToken: function (ctx) {
    return getToken(subscribeTokenEndpoint, ctx);
  },
})
sub.on('publication', (ctx) => {
  console.log('publication', ctx)
  chat.value.push(ctx)
})
sub.on('subscribing', () => subscribed.value = 'subscribing')
sub.on('subscribed', () => subscribed.value = 'subscribed')
sub.on('unsubscribed', () => subscribed.value = 'uns')

const handleLoginForm = async () => {
  centrifuge.connect()
}

centrifuge.on('connected', () => connected.value = true)

const presence = ref({})

const chat = ref([])

const handlePresence = () => {
  sub.presence().then(ctx => presence.value = ctx)
}

const handleSendMessage = () => sub.publish({data: 'test'});

const handleSubscribe = () => sub.subscribe();




</script>

<template>
  <main>
    <div class="card">
        <button @click="handleLoginForm">Connect</button>
        <button @click="handleSubscribe">Subscribe</button>
        <button @click="handlePresence" :disabled="!connected">Presence</button>
        <button @click="handleSendMessage" :disabled="!connected">Send</button>
    </div>
    <div class="card">
      Connected
      <pre>{{ connected }}</pre>
      Subscribed
      <pre>{{ subscribed }}</pre>
      Presence test:test
      <pre>{{ presence }}</pre>
    </div>
    <div class="card">
     <pre>{{ chat }}</pre>
    </div>
  </main>
</template>

<style scoped>

main {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

pre {
  background: darkslateblue;
  color: #f3f3f3;
  padding: 5px 10px;
  border-radius: 10px;
}

.card {
  padding: 15px;
  background: #ffffff;
  width: max-content;
}

button {
  display: block;
  margin-top: 15px;
}

input {
  margin-top: 5px;
  padding: 5px 10px;
}
</style>

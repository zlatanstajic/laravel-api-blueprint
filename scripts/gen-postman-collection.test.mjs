import assert from 'node:assert/strict';
import test from 'node:test';
import { generateCollection } from './gen-postman-collection.mjs';

function specification() {
  const todoBody = { content: { 'application/json': { schema: {
    type: 'object', required: ['title'], properties: {
      title: { type: 'string' },
      completed: { type: 'boolean' },
      description: { type: ['string', 'null'] },
    },
  } } } };
  return {
    openapi: '3.1.0', info: { title: 'Test API', version: '1.0.0' },
    servers: [{ url: 'http://localhost:8000/api' }],
    paths: {
      '/': { get: { responses: { 200: { description: 'Welcome' } } } },
      '/tokens': { post: {
        requestBody: { content: { 'application/json': { schema: {
          type: 'object', properties: { email: { type: 'string' }, password: { type: 'string' } },
        } } } }, responses: { 200: { description: 'Token' } },
      } },
      '/todos': { post: { requestBody: todoBody, responses: { 201: { description: 'Created' } } } },
      '/todos/{id}': { get: {
        parameters: [{ name: 'id', in: 'path', required: true, schema: { type: 'integer' } }],
        responses: { 200: { description: 'Todo' } },
      } },
    },
  };
}

function requests(collection) {
  return collection.item.flatMap(item => item.item ? requests(item) : [item]);
}

test('generation is deterministic and preserves portable authentication', async () => {
  const first = await generateCollection(specification());
  assert.equal(first, await generateCollection(specification()));
  const collection = JSON.parse(first);
  assert.equal(collection.info._postman_id, undefined);
  assert.equal(collection.auth.bearer[0].value, '{{ACCESS_TOKEN}}');
  const items = requests(collection);
  assert.equal(items.length, 4);
  const token = items.find(item => item.request.url.path.join('/') === 'tokens');
  assert.deepEqual(JSON.parse(token.request.body.raw), { email: '{{ADMIN_EMAIL}}', password: '{{ADMIN_PASSWORD}}' });
  assert.equal(token.request.auth.type, 'noauth');
  assert.match(token.event[0].script.exec.join('\n'), /pm.environment.set\("ACCESS_TOKEN"/);
  assert.match(token.event[0].script.exec.join('\n'), /pm.environment.unset\("ACCESS_TOKEN"/);
  const todo = items.find(item => item.request.method === 'POST' && item.request.url.path[0] === 'todos');
  assert.deepEqual(JSON.parse(todo.request.body.raw), { title: 'Example value', completed: false, description: null });
  const read = items.find(item => item.request.url.variable.length);
  assert.equal(read.request.url.variable[0].value, '{{TODO_ID}}');
  assert.equal(read.request.url.raw, '{{BASE_URL}}/todos/:id');
  for (const item of items) {
    assert.equal(item.id, undefined);
    assert.deepEqual(item.response, []);
  }
});

test('new operations and validation fields flow from OpenAPI', async () => {
  const spec = specification();
  spec.paths['/todos'].post.requestBody.content['application/json'].schema.properties.priority = { type: 'integer' };
  spec.paths['/extra'] = { get: { responses: { 200: { description: 'Extra' } } } };
  const items = requests(JSON.parse(await generateCollection(spec)));
  assert.equal(items.length, 5);
  const todo = items.find(item => item.request.method === 'POST' && item.request.url.path[0] === 'todos');
  assert.equal(JSON.parse(todo.request.body.raw).priority, 1);
});

test('invalid OpenAPI fails instead of producing a collection', async () => {
  await assert.rejects(generateCollection({}));
});

test('local schema references and supported field types produce valid JSON samples', async () => {
  const spec = specification();
  spec.components = { schemas: { Input: {
    type: 'object', properties: {
      tags: { type: 'array', items: { type: 'string' } },
      priority: { type: 'number', minimum: 2 },
      state: { type: 'string', enum: ['open', 'closed'] },
      archived: { type: 'boolean', default: true },
      label: { type: 'string', example: 'Label' },
      contact: { type: 'string', format: 'email' },
      empty: { type: 'null' },
      details: { type: 'object' },
    },
  } } };
  spec.paths['/todos'].post.requestBody.content['application/json'].schema = { $ref: '#/components/schemas/Input' };
  spec.paths['/todos'].post.tags = ['Todos'];
  spec.paths['/todos'].post.summary = 'Create todo';
  const todo = requests(JSON.parse(await generateCollection(spec))).find(item => item.name === 'Create todo');
  assert.deepEqual(JSON.parse(todo.request.body.raw), {
    tags: ['Example value'], priority: 2, state: 'open', archived: true,
    label: 'Label', contact: 'user@example.com', empty: null, details: {},
  });
});

test('query parameters and non-todo path variables are exported', async () => {
  const spec = specification();
  spec.paths['/extra/{key}'] = {
    parameters: [{ name: 'limit', in: 'query', required: true, schema: { type: 'integer' } }],
    patch: { parameters: [{ name: 'search', in: 'query', schema: { type: 'string' } }], responses: {} },
  };
  const item = requests(JSON.parse(await generateCollection(spec))).find(item => item.request.method === 'PATCH');
  assert.equal(item.request.url.variable[0].value, '1');
  assert.equal(item.request.url.raw, '{{BASE_URL}}/extra/:key?limit=1');
  assert.deepEqual(item.request.url.query, [
    { key: 'limit', value: '1', disabled: false },
    { key: 'search', value: 'Example value', disabled: true },
  ]);
});

test('unsupported, missing, remote, and recursive schemas fail explicitly', async () => {
  for (const schema of [
    { type: 'unsupported' },
    { $ref: '#/components/schemas/Missing' },
    { $ref: 'https://example.com/schema.json' },
  ]) {
    const spec = specification();
    spec.paths['/todos'].post.requestBody.content['application/json'].schema = schema;
    await assert.rejects(generateCollection(spec));
  }
  const spec = specification();
  const recursive = { type: 'object', properties: {} };
  recursive.properties.child = recursive;
  spec.paths['/todos'].post.requestBody.content['application/json'].schema = recursive;
  await assert.rejects(generateCollection(spec), /Recursive/);
  spec.paths['/todos'].post.requestBody.content = { 'text/plain': {} };
  await assert.rejects(generateCollection(spec), /JSON request bodies/);
});

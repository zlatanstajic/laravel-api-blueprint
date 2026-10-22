import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const collectionPath = 'postman/laravel-api-blueprint.postman_collection.json';
const methods = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

// Scramble emits local JSON Schema references; remote documents are never fetched.
function dereference(schema, specification) {
  if (!schema.$ref) return schema;
  if (!schema.$ref.startsWith('#/')) throw new Error('Only local schema references are supported.');
  const value = schema.$ref.slice(2).split('/').reduce((node, key) =>
    node?.[key.replaceAll('~1', '/').replaceAll('~0', '~')], specification);
  if (!value) throw new Error(`Missing schema reference: ${schema.$ref}`);
  return value;
}

function sample(schema, specification, ancestors = []) {
  if (ancestors.includes(schema)) throw new Error('Recursive request schemas need an explicit example.');
  if (Object.hasOwn(schema, 'example')) return schema.example;
  if (Object.hasOwn(schema, 'default')) return schema.default;
  if (schema.enum) return schema.enum[0];
  if (schema.$ref) return sample(dereference(schema, specification), specification, [...ancestors, schema]);
  if (Array.isArray(schema.type) && schema.type.includes('null')) return null;
  switch (schema.type) {
    case 'object':
      return Object.fromEntries(Object.entries(schema.properties ?? {}).map(([key, property]) =>
        [key, sample(property, specification, [...ancestors, schema])]));
    case 'array': return [sample(schema.items, specification, [...ancestors, schema])];
    case 'string': return schema.format === 'email' ? 'user@example.com' : 'Example value';
    case 'boolean': return false;
    case 'integer':
    case 'number': return schema.minimum ?? 1;
    case 'null': return null;
    default: throw new Error(`Unsupported request schema type: ${schema.type}`);
  }
}

export async function generateCollection(specification) {
  if (!specification.openapi?.startsWith('3.') || !specification.paths || !specification.info) {
    throw new Error('A Scramble OpenAPI 3 document is required.');
  }
  const collection = {
    info: {
      name: 'Laravel API Blueprint',
      schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
      description: 'Generated from Scramble OpenAPI. Regenerate with composer docs:postman.',
    },
    item: [],
    variable: [
      { key: 'BASE_URL', value: 'http://localhost:8000/api', type: 'string' },
      { key: 'TODO_ID', value: '1', type: 'string' },
    ],
    auth: { type: 'bearer', bearer: [{ key: 'token', value: '{{ACCESS_TOKEN}}', type: 'string' }] },
  };
  const folders = new Map();
  for (const [path, definition] of Object.entries(specification.paths).sort(([a], [b]) => a.localeCompare(b))) {
    for (const method of methods) {
      const operation = definition[method];
      if (!operation) continue;
      const tokenRequest = path === '/tokens' && method === 'post';
      const pathParts = path.slice(1).split('/').map(part => part.replace(/^\{(.+)\}$/, ':$1'));
      const variables = pathParts.filter(part => part.startsWith(':')).map(part => ({
        key: part.slice(1), value: part === ':id' ? '{{TODO_ID}}' : '1',
      }));
      const parameters = [...(definition.parameters ?? []), ...(operation.parameters ?? [])]
        .map(parameter => dereference(parameter, specification));
      const query = parameters.filter(parameter => parameter.in === 'query').map(parameter => ({
        key: parameter.name,
        value: String(sample(parameter.schema, specification)),
        disabled: !parameter.required,
      }));
      const queryString = new URLSearchParams(query.filter(parameter => !parameter.disabled)
        .map(parameter => [parameter.key, parameter.value])).toString();
      const request = {
        method: method.toUpperCase(),
        header: [{ key: 'Accept', value: 'application/json' }],
        url: {
          raw: '{{BASE_URL}}/' + pathParts.join('/') + (queryString ? '?' + queryString : ''),
          host: ['{{BASE_URL}}'], path: pathParts, query, variable: variables,
        },
        auth: tokenRequest || path === '/' ? { type: 'noauth' } : null,
        body: {},
      };
      if (operation.requestBody) {
        const body = dereference(operation.requestBody, specification);
        const schema = body.content?.['application/json']?.schema;
        if (!schema) throw new Error('Only JSON request bodies are supported.');
        const value = sample(schema, specification);
        if (tokenRequest) {
          value.email = '{{ADMIN_EMAIL}}';
          value.password = '{{ADMIN_PASSWORD}}';
        }
        request.body = { mode: 'raw', raw: JSON.stringify(value, null, 2), options: { raw: { language: 'json' } } };
        request.header.push({ key: 'Content-Type', value: 'application/json' });
      }
      const item = { name: operation.summary ?? operation.operationId ?? `${method.toUpperCase()} ${path}`, request, response: [] };
      if (tokenRequest) {
        item.event = [{ listen: 'test', script: { type: 'text/javascript', exec: [
          'if (pm.response.code === 200) {',
          '  pm.environment.set("ACCESS_TOKEN", pm.response.json().data.token);',
          '} else {',
          '  pm.environment.unset("ACCESS_TOKEN");',
          '}',
        ] } }];
      }
      const folder = operation.tags?.[0] ?? 'API';
      if (!folders.has(folder)) folders.set(folder, { name: folder, item: [] });
      folders.get(folder).item.push(item);
    }
  }
  collection.item = [...folders.values()];
  return JSON.stringify(collection, null, 2) + '\n';
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const specification = JSON.parse(await readFile('build/docs/api.json', 'utf8'));
    const output = await generateCollection(specification);
    if (process.argv.includes('--check')) {
      if (await readFile(collectionPath, 'utf8') !== output) {
        throw new Error('Postman collection is stale. Run composer docs:postman.');
      }
      console.log('Postman collection is current.');
    } else {
      await writeFile(collectionPath, output);
      console.log(`Generated ${collectionPath}.`);
    }
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}

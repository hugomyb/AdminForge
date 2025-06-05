import { EditorView, keymap } from '@codemirror/view'
import { EditorState } from '@codemirror/state'
import { sql } from '@codemirror/lang-sql'
import { autocompletion, completionKeymap } from '@codemirror/autocomplete'
import { defaultKeymap } from '@codemirror/commands'
import { basicSetup } from 'codemirror'

class SqlEditor {
    constructor(element, options = {}) {
        this.element = element
        this.options = {
            placeholder: '-- Saisissez votre requête SQL ici...',
            readOnly: false,
            schema: {},
            onExecute: null,
            onChange: null,
            ...options
        }
        
        this.view = null
        this.schema = this.options.schema
        this.init()
    }

    init() {
        // Configuration de base de l'éditeur
        const extensions = [
            basicSetup,
            sql({
                schema: this.schema,
                upperCaseKeywords: true
            }),
            autocompletion({
                override: [this.createCompletionSource()],
                activateOnTyping: true,
                maxRenderedOptions: 20
            }),
            keymap.of([
                ...defaultKeymap,
                ...completionKeymap,
                {
                    key: 'Ctrl-Enter',
                    run: () => {
                        if (this.options.onExecute) {
                            this.options.onExecute(this.getValue())
                        }
                        return true
                    }
                }
            ]),
            EditorView.updateListener.of((update) => {
                if (update.docChanged && this.options.onChange) {
                    this.options.onChange(this.getValue())
                }
            }),
            EditorView.theme({
                '&': {
                    fontSize: '14px',
                    fontFamily: 'Monaco, Menlo, "Ubuntu Mono", monospace'
                },
                '.cm-content': {
                    padding: '12px',
                    minHeight: '300px'
                },
                '.cm-focused': {
                    outline: '2px solid rgb(59 130 246)',
                    outlineOffset: '2px'
                },
                '.cm-editor': {
                    borderRadius: '8px',
                    border: '1px solid rgb(209 213 219)'
                },
                '.cm-editor.cm-focused': {
                    borderColor: 'rgb(59 130 246)',
                    boxShadow: '0 0 0 1px rgb(59 130 246)'
                }
            })
        ]

        // Ajouter le mode lecture seule si nécessaire
        if (this.options.readOnly) {
            extensions.push(EditorState.readOnly.of(true))
        }

        // Créer l'état de l'éditeur
        const state = EditorState.create({
            doc: this.element.value || '',
            extensions
        })

        // Créer la vue de l'éditeur
        this.view = new EditorView({
            state,
            parent: this.element.parentNode
        })

        // Cacher l'élément textarea original
        this.element.style.display = 'none'
    }

    createCompletionSource() {
        return (context) => {
            const word = context.matchBefore(/\w*/)
            if (!word) return null

            const options = []

            // Mots-clés SQL de base
            const sqlKeywords = [
                'SELECT', 'FROM', 'WHERE', 'JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN',
                'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'OFFSET', 'INSERT', 'UPDATE',
                'DELETE', 'CREATE', 'ALTER', 'DROP', 'INDEX', 'TABLE', 'DATABASE',
                'AND', 'OR', 'NOT', 'IN', 'EXISTS', 'BETWEEN', 'LIKE', 'IS NULL',
                'IS NOT NULL', 'DISTINCT', 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX',
                'CASE', 'WHEN', 'THEN', 'ELSE', 'END', 'AS', 'ON', 'USING'
            ]

            // Ajouter les mots-clés SQL
            sqlKeywords.forEach(keyword => {
                if (keyword.toLowerCase().startsWith(word.text.toLowerCase())) {
                    options.push({
                        label: keyword,
                        type: 'keyword',
                        info: `Mot-clé SQL: ${keyword}`
                    })
                }
            })

            // Ajouter les tables du schéma (format: {table1: [col1, col2], table2: [col3, col4]})
            if (this.schema && typeof this.schema === 'object') {
                Object.keys(this.schema).forEach(tableName => {
                    if (tableName.toLowerCase().startsWith(word.text.toLowerCase())) {
                        options.push({
                            label: tableName,
                            type: 'class',
                            info: `Table: ${tableName}`
                        })
                    }
                })

                // Ajouter les colonnes de toutes les tables
                Object.entries(this.schema).forEach(([tableName, columns]) => {
                    if (Array.isArray(columns)) {
                        columns.forEach(columnName => {
                            if (columnName.toLowerCase().startsWith(word.text.toLowerCase())) {
                                options.push({
                                    label: columnName,
                                    type: 'property',
                                    info: `Colonne de ${tableName}: ${columnName}`
                                })
                            }
                        })
                    }
                })
            }

            // Fonctions SQL courantes
            const sqlFunctions = [
                'NOW()', 'CURDATE()', 'CURTIME()', 'DATE()', 'TIME()', 'YEAR()', 'MONTH()', 'DAY()',
                'CONCAT()', 'SUBSTRING()', 'LENGTH()', 'UPPER()', 'LOWER()', 'TRIM()',
                'COALESCE()', 'IFNULL()', 'NULLIF()', 'GREATEST()', 'LEAST()'
            ]

            sqlFunctions.forEach(func => {
                const funcName = func.replace('()', '')
                if (funcName.toLowerCase().startsWith(word.text.toLowerCase())) {
                    options.push({
                        label: func,
                        type: 'function',
                        info: `Fonction SQL: ${func}`
                    })
                }
            })

            return {
                from: word.from,
                options: options.slice(0, 50), // Limiter à 50 suggestions
                validFor: /^\w*$/
            }
        }
    }

    updateSchema(newSchema) {
        this.schema = newSchema
        // Pour l'instant, on recrée l'éditeur avec le nouveau schéma
        // Une approche plus sophistiquée serait de reconfigurer dynamiquement
        if (this.view) {
            const currentValue = this.getValue()
            this.destroy()
            this.init()
            this.setValue(currentValue)
        }
    }

    getValue() {
        return this.view.state.doc.toString()
    }

    setValue(value) {
        this.view.dispatch({
            changes: {
                from: 0,
                to: this.view.state.doc.length,
                insert: value
            }
        })
        
        // Synchroniser avec l'élément textarea original
        this.element.value = value
    }

    setReadOnly(readOnly) {
        this.view.dispatch({
            effects: [
                EditorState.readOnly.reconfigure(readOnly)
            ]
        })
    }

    focus() {
        this.view.focus()
    }

    destroy() {
        if (this.view) {
            this.view.destroy()
            this.element.style.display = ''
        }
    }
}

// Fonction d'initialisation globale
window.initSqlEditor = function(elementId, options = {}) {
    if (!elementId) {
        console.error('Element ID is required')
        return null
    }

    const element = document.getElementById(elementId)
    if (!element) {
        console.error(`Element with id "${elementId}" not found`)
        return null
    }

    return new SqlEditor(element, options)
}

export default SqlEditor

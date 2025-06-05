// Import des modules CodeMirror installés via npm
import { EditorView, keymap } from '@codemirror/view'
import { EditorState } from '@codemirror/state'
import { sql } from '@codemirror/lang-sql'
import { autocompletion, completionKeymap } from '@codemirror/autocomplete'
import { defaultKeymap } from '@codemirror/commands'
import { basicSetup } from 'codemirror'

// Exposer les modules CodeMirror globalement
window.CodeMirrorModules = {
    EditorView, 
    EditorState, 
    sql, 
    autocompletion, 
    completionKeymap, 
    defaultKeymap, 
    basicSetup, 
    keymap
};

console.log('Modules CodeMirror chargés:', Object.keys(window.CodeMirrorModules));

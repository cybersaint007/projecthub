<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $project->name }}</h2>
                    @if($project->trashed())
                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded">Deleted</span>
                    @endif
                    @include('projects.partials.access-badges', ['project' => $project, 'user' => auth()->user()])
                </div>
                @if($project->description)
                    <p class="text-sm text-gray-500 mt-1">{{ $project->description }}</p>
                @endif
            </div>
            @php $userRole = $project->roleFor(auth()->user()); @endphp
            <div class="flex gap-2 flex-wrap">
                @if($project->trashed())
                    @if(Auth::user()->isAdmin())
                        <form method="POST" action="{{ route('projects.restore', $project) }}" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700" onclick="return confirm('Are you sure you want to restore this project? This will also restore all related epics and tasks.')">Restore Project</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('project-files.index', $project) }}" class="px-3 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">Files</a>
                    @if(($userRole === 'owner' || $userRole === 'editor') || Auth::user()->isAdmin())
                        <a href="{{ route('epics.create', $project) }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">New Epic</a>
                    @endif
                    @if(($userRole && $userRole !== 'viewer') || Auth::user()->isAdmin())
                        <a href="{{ route('projects.edit', $project) }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Edit</a>
                    @endif
                    @if($userRole === 'owner' || Auth::user()->isAdmin())
                        <a href="{{ route('projects.access', $project) }}" class="px-3 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">Manage access</a>
                    @endif
                    @if(Auth::user()->isAdmin())
                        <form method="POST" action="{{ route('projects.destroy', $project) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700" onclick="return confirm('Are you sure you want to delete this project? This will also delete all related epics and tasks. This action can be undone by restoring the project.')">Delete Project</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </x-slot>

    @if($project->users->isNotEmpty() && Auth::user()->isAdmin())
        <div class="mb-4 bg-white shadow-sm sm:rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Assigned Users</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($project->users as $user)
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">{{ $user->name }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @php
        $userRole = $project->roleFor(auth()->user());
        $viewMode = request('view', 'list');
        if (!in_array($viewMode, ['list', 'kanban'], true)) {
            $viewMode = 'list';
        }
        $canUpdate = ($userRole === 'owner' || $userRole === 'editor') || Auth::user()->isAdmin();
    @endphp

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <h3 class="text-lg font-medium">Epics</h3>
                <div class="flex rounded-lg border border-gray-200 p-0.5 bg-gray-50" role="group">
                    <a href="{{ route('projects.show', [$project, 'view' => 'list']) }}" class="px-3 py-1.5 text-sm font-medium rounded-md {{ $viewMode === 'list' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-800' }}">List</a>
                    <a href="{{ route('projects.show', [$project, 'view' => 'kanban']) }}" class="px-3 py-1.5 text-sm font-medium rounded-md {{ $viewMode === 'kanban' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-800' }}">Kanban</a>
                </div>
            </div>
            @if($viewMode === 'kanban')
                @include('projects.partials.kanban-view', ['project' => $project, 'userRole' => $userRole])
            @else
                @include('projects.partials.list-view', ['project' => $project, 'userRole' => $userRole])
            @endif
        </div>
    </div>

    @if($canUpdate && !$project->trashed())
        <div id="reorder-config" data-reorder-config='@json(['projectId' => $project->id, 'viewMode' => $viewMode])' style="display:none"></div>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" crossorigin="anonymous" onload="if(window.__reorderInit)window.__reorderInit()"></script>
        <script>
window.__reorderInit=function(){
var el=document.getElementById('reorder-config');
var meta=document.querySelector('meta[name="csrf-token"]');
if(!window.Sortable||!el||!meta)return;
var cfg=JSON.parse(el.getAttribute('data-reorder-config')||'{}');
var projectId=cfg.projectId, csrfToken=meta.getAttribute('content')||'';
if(!projectId||!csrfToken)return;
function toast(msg,err){var ex=document.getElementById('reorder-toast');if(ex)ex.remove();var d=document.createElement('div');d.id='reorder-toast';d.className='fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 text-sm '+(err?'bg-red-600':'bg-green-600')+' text-white';d.textContent=msg;document.body.appendChild(d);setTimeout(function(){d.remove();},3000);}
var taskDebounce;
function buildCols(){var cols=[];document.querySelectorAll('.task-sortable').forEach(function(c){var eid=c.dataset.epicId||c.getAttribute('data-epic-id');if(!eid)return;var tids=[];c.querySelectorAll('.task-row').forEach(function(r){var id=r.dataset.taskId||r.getAttribute('data-task-id');if(id)tids.push(parseInt(id,10));});cols.push({epic_id:parseInt(eid,10),task_ids:tids});});return cols;}
function saveTasks(){var body=JSON.stringify({columns:buildCols()});fetch('/projects/'+projectId+'/tasks/reorder',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken,'X-Requested-With':'XMLHttpRequest'},body:body}).then(function(r){if(r.ok){toast('Order saved.');return;}r.json().then(function(d){toast(d.message||(d.errors?Object.values(d.errors).flat().join(' '):'Failed to save order.'),true);});}).catch(function(){toast('Failed to save order.',true);});}
function debounceSave(){if(taskDebounce)clearTimeout(taskDebounce);taskDebounce=setTimeout(function(){taskDebounce=null;saveTasks();},200);}
var listEl=document.getElementById('epic-sortable'), kanbanEl=document.getElementById('kanban-columns');
var cont=listEl||kanbanEl;
var taskSortables=document.querySelectorAll('.task-sortable');
if(cont){
var isKanban=!!kanbanEl, itemSel=isKanban?'.kanban-column':'.epic-row';
var epicOpts={draggable:isKanban?'.kanban-column':'.epic-row',animation:150,forceFallback:true,filter:'a',preventOnFilter:false,onEnd:function(){var ids=Array.prototype.map.call(cont.querySelectorAll(itemSel),function(r){return parseInt(r.dataset.epicId||r.getAttribute('data-epic-id'),10);}).filter(Boolean);if(ids.length===0)return;fetch('/projects/'+projectId+'/epics/reorder',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({epic_ids:ids})}).then(function(r){if(r.ok){toast('Order saved.');return;}r.json().then(function(d){toast(d.message||(d.errors&&d.errors.epic_ids?d.errors.epic_ids.join(' '):'Failed'),true);});}).catch(function(){toast('Failed to save order.',true);});}};
if(!isKanban)epicOpts.filter='a, .task-sortable';
document.querySelectorAll('.task-sortable').forEach(function(c){try{window.Sortable.create(c,{group:'tasks',animation:150,forceFallback:true,filter:'a',preventOnFilter:false,onEnd:debounceSave});}catch(e){}});
try{window.Sortable.create(cont,epicOpts);}catch(e){}
}
};
if(window.Sortable)window.__reorderInit();
        </script>
    @endif
</x-app-layout>

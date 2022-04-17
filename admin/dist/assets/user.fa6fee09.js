var T=Object.defineProperty,U=Object.defineProperties;var I=Object.getOwnPropertyDescriptors;var D=Object.getOwnPropertySymbols;var A=Object.prototype.hasOwnProperty,B=Object.prototype.propertyIsEnumerable;var k=(a,o,l)=>o in a?T(a,o,{enumerable:!0,configurable:!0,writable:!0,value:l}):a[o]=l,w=(a,o)=>{for(var l in o||(o={}))A.call(o,l)&&k(a,l,o[l]);if(D)for(var l of D(o))B.call(o,l)&&k(a,l,o[l]);return a},E=(a,o)=>U(a,I(o));import{B as N,u as R,r as P,o as j,t as z,e as d,y as F,f,q as x,w as n,E as M,h,k as t,g as L,A as Q,F as Y,z as G,l as V,p as H,m as J}from"./vendor.7b1bb722.js";import{_ as K,a as q}from"./index.ca628989.js";import{u as O,r as W,a as X}from"./table.77a09864.js";const Z={name:"ExamUserTable",setup(){const a=N(null),o=R(),l=O();let r=P({exams:[]});j(()=>{q.listExamAll().then(e=>{r.exams=e.data}),i()});const i=async()=>{l.loading=!0;let e=await q.listExamUser(l.query);W(e,l),l.loading=!1},S=()=>{o.push({name:"user-form"})},u=e=>{o.push({name:"user-form",query:{id:e}})},m=async e=>{let _=await q.deleteExam(e);M.success(_.msg),l.query.page=1,await i()},c=e=>{l.multipleSelection=e},p=e=>{l.query.page=e,i()},b=e=>{X(e,l),i()},s=e=>{o.push({name:"user-detail",query:{id:e}})},g=(e,_)=>e.user.username,y=(e,_)=>e.exam.name,v=(e,_)=>e.downloaded_text,C=()=>{l.query.is_done="",l.query.status="",l.query.exam_id=""};return E(w({},z(l)),{multipleTable:a,extraData:r,handleSelectionChange:c,handleAdd:S,handleEdit:u,handleDelete:m,handleDetail:s,fetchTableData:i,changePage:p,handleSortChange:b,formatColumnUser:g,formatColumnExam:y,formatColumnDownloaded:v,handleReset:C})}},$=a=>(H("data-v-0556c6df"),a=a(),J(),a),ee={class:"nexus-table-header"},ae={class:"left"},le=V("Query"),te=V("Reset"),oe=$(()=>h("div",{class:"right"},null,-1)),ne=["onClick"];function re(a,o,l,r,i,S){const u=d("el-option"),m=d("el-select"),c=d("el-form-item"),p=d("el-button"),b=d("el-form"),s=d("el-table-column"),g=d("el-table"),y=d("el-pagination"),v=d("el-card"),C=F("loading");return f(),x(v,null,{header:n(()=>[h("div",ee,[h("div",ae,[t(b,{inline:!0,model:a.query},{default:n(()=>[t(c,{label:""},{default:n(()=>[t(m,{modelValue:a.query.exam_id,"onUpdate:modelValue":o[0]||(o[0]=e=>a.query.exam_id=e),filterable:"",placeholder:"Exam",clearable:""},{default:n(()=>[(f(!0),L(Y,null,Q(r.extraData.exams,e=>(f(),x(u,{key:e.id,label:e.name,value:e.id},null,8,["label","value"]))),128))]),_:1},8,["modelValue"])]),_:1}),t(c,{label:""},{default:n(()=>[t(m,{modelValue:a.query.is_done,"onUpdate:modelValue":o[1]||(o[1]=e=>a.query.is_done=e),filterable:"",placeholder:"IsDone",clearable:""},{default:n(()=>[t(u,{label:"Yes",value:"1"}),t(u,{label:"No",value:"0"})]),_:1},8,["modelValue"])]),_:1}),t(c,{label:""},{default:n(()=>[t(m,{modelValue:a.query.status,"onUpdate:modelValue":o[2]||(o[2]=e=>a.query.status=e),filterable:"",placeholder:"Status",clearable:""},{default:n(()=>[t(u,{label:"Avoided",value:"-1"}),t(u,{label:"Normal",value:"0"}),t(u,{label:"Finished",value:"1"})]),_:1},8,["modelValue"])]),_:1}),t(c,null,{default:n(()=>[t(p,{type:"primary",onClick:r.fetchTableData},{default:n(()=>[le]),_:1},8,["onClick"]),t(p,{type:"primary",onClick:r.handleReset},{default:n(()=>[te]),_:1},8,["onClick"])]),_:1})]),_:1},8,["model"])]),oe])]),default:n(()=>[G((f(),x(g,{ref:"multipleTable",data:a.tableData,"tooltip-effect":"dark",onSortChange:r.handleSortChange,onSelectionChange:r.handleSelectionChange},{default:n(()=>[t(s,{type:"selection",width:"55"}),t(s,{prop:"id",label:"Id",width:"100",sortable:"custom"}),t(s,{prop:"exam_id",label:"Exam",formatter:r.formatColumnExam},null,8,["formatter"]),t(s,{prop:"uid",label:"User",formatter:r.formatColumnUser},null,8,["formatter"]),t(s,{prop:"is_done_text",label:"Is done"}),t(s,{prop:"status_text",label:"Status"}),t(s,{prop:"created_at",label:"Created At"}),t(s,{label:"Action",width:"100"},{default:n(e=>[h("a",{style:{cursor:"pointer","margin-right":"10px"},onClick:_=>r.handleDetail(e.row.uid)},"Detail",8,ne)]),_:1})]),_:1},8,["data","onSortChange","onSelectionChange"])),[[C,a.loading]]),t(y,{background:"",layout:"prev, pager, next",total:a.total,"page-size":a.perPage,"current-page":a.currentPage,onCurrentChange:r.changePage},null,8,["total","page-size","current-page","onCurrentChange"])]),_:1})}var ce=K(Z,[["render",re],["__scopeId","data-v-0556c6df"]]);export{ce as default};

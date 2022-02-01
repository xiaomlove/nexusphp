var k=Object.defineProperty,T=Object.defineProperties;var q=Object.getOwnPropertyDescriptors;var C=Object.getOwnPropertySymbols;var I=Object.prototype.hasOwnProperty,U=Object.prototype.propertyIsEnumerable;var v=(e,o,a)=>o in e?k(e,o,{enumerable:!0,configurable:!0,writable:!0,value:a}):e[o]=a,x=(e,o)=>{for(var a in o||(o={}))I.call(o,a)&&v(e,a,o[a]);if(C)for(var a of C(o))U.call(o,a)&&v(e,a,o[a]);return e},w=(e,o)=>T(e,q(o));import{B,u as P,o as j,t as z,e as c,y as A,f as y,q as S,w as d,E as M,z as N,k as l,h as i,p as R,m as V}from"./vendor.51c5b88d.js";import{_ as F,a as D}from"./index.18f7a70d.js";import{u as G,r as H,a as J}from"./table.848ed703.js";const K={name:"ExamUserTable",setup(){const e=B(null),o=P(),a=G();j(()=>{n()});const n=async()=>{a.loading=!0;let t=await D.listExamUser(a.query);H(t,a),a.loading=!1},g=()=>{o.push({name:"user-form"})},f=t=>{o.push({name:"user-form",query:{id:t}})},r=async t=>{let s=await D.deleteExam(t);M.success(s.msg),a.query.page=1,await n()},u=t=>{a.multipleSelection=t},m=t=>{a.query.page=t,n()},p=t=>{J(t,a),n()},_=t=>{o.push({name:"user-detail",query:{id:t}})},h=(t,s)=>t.user.username,b=(t,s)=>t.exam.name,E=(t,s)=>t.downloaded_text;return w(x({},z(a)),{multipleTable:e,handleSelectionChange:u,handleAdd:g,handleEdit:f,handleDelete:r,handleDetail:_,fetchTableData:n,changePage:m,handleSortChange:p,formatColumnUser:h,formatColumnExam:b,formatColumnDownloaded:E})}},L=e=>(R("data-v-610bee21"),e=e(),V(),e),O=L(()=>i("div",{class:"nexus-table-header"},[i("div",{class:"left"}),i("div",{class:"right"})],-1)),Q=["onClick"];function W(e,o,a,n,g,f){const r=c("el-table-column"),u=c("el-table"),m=c("el-pagination"),p=c("el-card"),_=A("loading");return y(),S(p,null,{header:d(()=>[O]),default:d(()=>[N((y(),S(u,{ref:"multipleTable",data:e.tableData,"tooltip-effect":"dark",onSortChange:n.handleSortChange,onSelectionChange:n.handleSelectionChange},{default:d(()=>[l(r,{type:"selection",width:"55"}),l(r,{prop:"id",label:"Id",width:"60",sortable:"custom"}),l(r,{prop:"exam_id",label:"Exam",formatter:n.formatColumnExam},null,8,["formatter"]),l(r,{prop:"uid",label:"User",formatter:n.formatColumnUser},null,8,["formatter"]),l(r,{prop:"is_done_text",label:"Is done"}),l(r,{prop:"created_at",label:"Created At"}),l(r,{label:"Action",width:"100"},{default:d(h=>[i("a",{style:{cursor:"pointer","margin-right":"10px"},onClick:b=>n.handleDetail(h.row.uid)},"Detail",8,Q)]),_:1})]),_:1},8,["data","onSortChange","onSelectionChange"])),[[_,e.loading]]),l(m,{background:"",layout:"prev, pager, next",total:e.total,"page-size":e.perPage,"current-page":e.currentPage,onCurrentChange:n.changePage},null,8,["total","page-size","current-page","onCurrentChange"])]),_:1})}var ee=F(K,[["render",W],["__scopeId","data-v-610bee21"]]);export{ee as default};
